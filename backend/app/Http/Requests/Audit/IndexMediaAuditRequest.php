<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use App\Actions\Audit\Data\MediaAuditFilters;
use App\Enums\MediaAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de `GET /api/v1/audit-logs/media`. Mesmas regras de IndexFormAuditRequest para
 * usuário (uuid, nunca id sequencial) e datas, com o tipo de acontecimento no lugar do tipo de
 * formulário.
 */
final class IndexMediaAuditRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['nullable', Rule::enum(MediaAuditEvent::class)],
            'user' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
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
            'event.enum' => 'Tipo de acontecimento desconhecido.',
            'user.exists' => 'Usuário não encontrado.',
            'from.date_format' => 'Informe a data inicial no formato aaaa-mm-dd.',
            'to.date_format' => 'Informe a data final no formato aaaa-mm-dd.',
            'to.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ];
    }

    public function filters(): MediaAuditFilters
    {
        return new MediaAuditFilters(
            event: $this->filled('event') ? MediaAuditEvent::from($this->string('event')->value()) : null,
            causer: $this->filled('user')
                ? User::query()->where('uuid', $this->string('user')->value())->first()
                : null,
            from: $this->filled('from') ? $this->string('from')->value() : null,
            to: $this->filled('to') ? $this->string('to')->value() : null,
            perPage: min($this->integer('per_page', 25), 100),
        );
    }
}
