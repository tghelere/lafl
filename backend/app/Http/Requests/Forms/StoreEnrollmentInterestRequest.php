<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\EnrollmentInterestData;
use App\Enums\ChildAgeRange;
use App\Enums\DesiredPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulário público, sem Policy — qualquer visitante pode enviar (ver
 * docs/estrutura-site.md §2.1). O campo de honeypot (App\Support\Honeypot) não entra nas
 * regras de propósito, ver o Controller.
 */
final class StoreEnrollmentInterestRequest extends FormRequest
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
            'guardian_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'child_age_range' => ['required', Rule::enum(ChildAgeRange::class)],
            'desired_period' => ['required', Rule::enum(DesiredPeriod::class)],
            // Rótulo no front precisa deixar explícito: dados da criança são coletados
            // presencialmente. Mesmo assim, o campo é cifrado por precaução (ver
            // docs/dominio.md).
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
            'guardian_name.required' => 'Informe seu nome.',
            'phone.required' => 'Informe um telefone para contato.',
            'email.required' => 'Informe um e-mail para contato.',
            'email.email' => 'Informe um e-mail válido.',
            'child_age_range.required' => 'Informe a faixa etária da criança.',
            'desired_period.required' => 'Informe o período pretendido.',
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): EnrollmentInterestData
    {
        return new EnrollmentInterestData(
            guardianName: $this->string('guardian_name')->value(),
            phone: $this->string('phone')->value(),
            email: $this->string('email')->value(),
            childAgeRange: ChildAgeRange::from($this->string('child_age_range')->value()),
            desiredPeriod: DesiredPeriod::from($this->string('desired_period')->value()),
            message: $this->filled('message') ? $this->string('message')->value() : null,
            ip: (string) $this->ip(),
        );
    }
}
