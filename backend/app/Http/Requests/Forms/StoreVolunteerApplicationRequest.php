<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\VolunteerApplicationData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreVolunteerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'availability' => ['required', 'string', 'max:255'],
            'interest_area' => ['required', 'string', 'max:255'],
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
            'name.required' => 'Informe seu nome.',
            'phone.required' => 'Informe um telefone para contato.',
            'email.required' => 'Informe um e-mail para contato.',
            'email.email' => 'Informe um e-mail válido.',
            'availability.required' => 'Informe sua disponibilidade.',
            'interest_area.required' => 'Informe a área de interesse.',
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): VolunteerApplicationData
    {
        return new VolunteerApplicationData(
            name: $this->string('name')->value(),
            phone: $this->string('phone')->value(),
            email: $this->string('email')->value(),
            availability: $this->string('availability')->value(),
            interestArea: $this->string('interest_area')->value(),
            message: $this->filled('message') ? $this->string('message')->value() : null,
            ip: (string) $this->ip(),
        );
    }
}
