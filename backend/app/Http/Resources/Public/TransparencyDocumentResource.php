<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Models\TransparencyDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público — nunca expõe file_path (detalhe interno de armazenamento) nem timestamp
 * de auditoria (ver docs/estrutura-site.md, Parte 3).
 *
 * @mixin TransparencyDocument
 */
final class TransparencyDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'year' => $this->year,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'file_size' => $this->file_size,
            'download_count' => $this->download_count,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
