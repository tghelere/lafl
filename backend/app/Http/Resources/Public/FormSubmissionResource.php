<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resposta pública de sucesso, comum às seis criações de formulário — nunca ecoa nenhum
 * campo pessoal enviado, só a confirmação de que o registro existe.
 *
 * O resource embrulhado tanto pode ser uma das seis entidades reais quanto o objeto forjado
 * de App\Support\Honeypot::decoySubmission() (resposta idêntica sem gravar nada) — por isso
 * `data_get()` em vez de propriedade mágica, que só existiria no model real.
 */
final class FormSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $createdAt = data_get($this->resource, 'created_at');

        return [
            'uuid' => data_get($this->resource, 'uuid'),
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : $createdAt,
        ];
    }
}
