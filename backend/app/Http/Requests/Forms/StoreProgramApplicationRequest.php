<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\ProgramApplicationData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aviso de interesse na Escola de Contraturno — o programa ainda não abriu inscrições (ver
 * docs/contexto.md), então só coleta contato do responsável para avisar quando abrirem.
 * Nenhum dado da criança ou adolescente entra aqui (ver ADR 0007).
 */
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
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): ProgramApplicationData
    {
        return new ProgramApplicationData(
            guardianName: $this->string('guardian_name')->value(),
            phone: $this->string('phone')->value(),
            ip: (string) $this->ip(),
        );
    }
}
