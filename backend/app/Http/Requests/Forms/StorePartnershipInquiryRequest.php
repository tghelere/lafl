<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\PartnershipInquiryData;
use App\Enums\PartnershipSupportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePartnershipInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Limpa a máscara do CNPJ (pontos, barra, hífen) antes de validar — o blind index (ver
     * App\Models\PartnershipInquiry) só detecta duplicidade se o valor normalizado for
     * sempre o mesmo formato.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tax_id')) {
            $this->merge(['tax_id' => preg_replace('/\D/', '', (string) $this->input('tax_id'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'tax_id' => ['required', 'digits:14'],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'support_type' => ['required', Rule::enum(PartnershipSupportType::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Informe o nome da empresa.',
            'tax_id.required' => 'Informe o CNPJ.',
            'tax_id.digits' => 'Informe um CNPJ válido, com 14 dígitos.',
            'contact_name.required' => 'Informe o nome do contato.',
            'phone.required' => 'Informe um telefone para contato.',
            'email.required' => 'Informe um e-mail para contato.',
            'email.email' => 'Informe um e-mail válido.',
            'support_type.required' => 'Informe o tipo de apoio pretendido.',
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): PartnershipInquiryData
    {
        return new PartnershipInquiryData(
            companyName: $this->string('company_name')->value(),
            taxId: $this->string('tax_id')->value(),
            contactName: $this->string('contact_name')->value(),
            phone: $this->string('phone')->value(),
            email: $this->string('email')->value(),
            supportType: PartnershipSupportType::from($this->string('support_type')->value()),
            message: $this->filled('message') ? $this->string('message')->value() : null,
            ip: (string) $this->ip(),
        );
    }
}
