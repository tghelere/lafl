<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\ProgramApplicationData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProgramApplicationRequest extends FormRequest
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
            // Faixa etária exata do programa é [CONFIRMAR] (ver docs/contexto.md); os limites
            // abaixo são só uma checagem de sanidade de formulário, não um critério oficial.
            'teen_age' => ['required', 'integer', 'min:10', 'max:19'],
            // Rótulo e obrigatoriedade [VALIDAR] com a instituição (ver docs/roadmap.md).
            'school' => ['nullable', 'string', 'max:255'],
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
            'teen_age.required' => 'Informe a idade do adolescente.',
            'teen_age.integer' => 'Informe a idade em anos completos.',
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): ProgramApplicationData
    {
        return new ProgramApplicationData(
            guardianName: $this->string('guardian_name')->value(),
            phone: $this->string('phone')->value(),
            email: $this->string('email')->value(),
            teenAge: $this->integer('teen_age'),
            school: $this->filled('school') ? $this->string('school')->value() : null,
            message: $this->filled('message') ? $this->string('message')->value() : null,
            ip: (string) $this->ip(),
        );
    }
}
