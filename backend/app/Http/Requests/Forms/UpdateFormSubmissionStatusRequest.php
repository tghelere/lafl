<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Enums\FormSubmissionStatus;
use App\Http\Requests\Forms\Concerns\ResolvesBoundSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Compartilhado pelas cinco rotas `PATCH /api/v1/{recurso}/{uuid}/status` (ver
 * docs/estrutura-site.md §4.5) — a regra de validação e a checagem de autorização são
 * idênticas nas cinco, só o model vinculado à rota muda. `authorize()` pega o model já
 * resolvido pelo route model binding (ver Concerns\ResolvesBoundSubmission), em vez de
 * repetir esta classe cinco vezes só para trocar o nome do parâmetro.
 */
final class UpdateFormSubmissionStatusRequest extends FormRequest
{
    use ResolvesBoundSubmission;

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
}
