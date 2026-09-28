<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Actions\Audit\Data\FormAuditFilters;
use App\Enums\FormSubmissionType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de `GET /api/v1/audit-logs` (ver docs/estrutura-site.md §4.5).
 *
 * `user` é o uuid da conta, nunca o id sequencial (CLAUDE.md, regra 4) — a resolução para o model
 * acontece em `filters()`, e um uuid inexistente vira erro de validação em vez de filtro
 * silenciosamente ignorado, que devolveria o log inteiro para quem pediu o de uma pessoa.
 */
final class IndexFormAuditRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(FormSubmissionType::class)],
            'user' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'record' => ['nullable', 'uuid'],
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
            'type.enum' => 'Tipo de formulário desconhecido.',
            'user.exists' => 'Usuário não encontrado.',
            'from.date_format' => 'Informe a data inicial no formato aaaa-mm-dd.',
            'to.date_format' => 'Informe a data final no formato aaaa-mm-dd.',
            'to.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ];
    }

    public function filters(): FormAuditFilters
    {
        return new FormAuditFilters(
            type: $this->filled('type') ? FormSubmissionType::from($this->string('type')->value()) : null,
            causer: $this->filled('user')
                ? User::query()->where('uuid', $this->string('user')->value())->first()
                : null,
            from: $this->filled('from') ? $this->string('from')->value() : null,
            to: $this->filled('to') ? $this->string('to')->value() : null,
            record: $this->filled('record') ? $this->string('record')->value() : null,
            perPage: min($this->integer('per_page', 25), 100),
        );
    }
}
