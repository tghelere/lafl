<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Enums\FormSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Compartilhado pelas seis rotas `PATCH /api/v1/{recurso}/{uuid}/status` (ver
 * docs/estrutura-site.md §4.5) — a regra de validação e a checagem de autorização são
 * idênticas nas seis, só o model vinculado à rota muda. `authorize()` pega o model já
 * resolvido pelo route model binding, seja qual for o nome do parâmetro da rota, em vez de
 * repetir esta classe seis vezes só para trocar o nome do parâmetro.
 */
final class UpdateFormSubmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->boundSubmission();

        return $submission !== null && ($this->user()?->can('update', $submission) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FormSubmissionStatus::class)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status.',
        ];
    }

    private function boundSubmission(): ?Model
    {
        foreach ($this->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }
}
