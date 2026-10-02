<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Models\Partner;
use App\Support\Media\MediaUrl;
use App\Support\Media\MediaVariants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público: nome, logo e link. Nada de uuid de mídia além do endereço público da
 * imagem, nem timestamps nem ordem — a ordem é a da própria lista.
 *
 * @mixin Partner
 */
final class PartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $media = $this->media;

        return [
            'name' => $this->name,
            'url' => $this->url,
            'logo' => [
                'src' => MediaUrl::derivative($media->uuid, MediaVariants::pick($media->widths, 400)),
                'srcset' => MediaUrl::srcset($media->uuid, $media->widths),
                'width' => $media->width,
                'height' => $media->height,
            ],
        ];
    }
}
