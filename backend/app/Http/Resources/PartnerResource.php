<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Partner;
use App\Support\Media\MediaVariants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
final class PartnerResource extends JsonResource
{
    /**
     * `logo_url` é a rota AUTENTICADA da biblioteca (a mesma de MediaResource::preview_url): é o
     * que o painel mostra, inclusive de parceiro inativo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->media;

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'url' => $this->url,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'logo_url' => route('media.file', [
                'media' => $media->uuid,
                'variant' => MediaVariants::pick($media->widths, 640).'.webp',
            ]).'?v='.$media->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
