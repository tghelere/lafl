<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Enums\FormSubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Compartilhado pelas cinco rotas `GET /api/v1/{recurso}` de formulário recebido (ver
 * docs/estrutura-site.md §4.5) — os filtros e a paginação são idênticos nas cinco, só o model
 * listado muda.
 *
 * Existe para que a entrada da listagem passe por FormRequest como qualquer outra (regra 5 do
 * CLAUDE.md), e principalmente para que `from`/`to` cheguem no formato que
 * App\Support\InstitutionalTime espera: sem `date_format:Y-m-d`, um valor qualquer na query
 * string chegaria ao `CarbonImmutable::parse()` e viraria exceção de 500 em vez de 422.
 *
 * A autorização NÃO mora aqui — cada controller chama `Gate::authorize('viewAny', ...)` com a
 * sua própria classe de model, que é o que a Policy precisa saber.
 */
final class IndexFormSubmissionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(FormSubmissionStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.enum' => 'Status desconhecido.',
            'from.date_format' => 'Informe a data inicial no formato aaaa-mm-dd.',
            'to.date_format' => 'Informe a data final no formato aaaa-mm-dd.',
            'to.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ];
    }
}
