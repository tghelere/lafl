<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Support\Media\MediaUrl;
use App\Support\Media\MediaVariants;

/**
 * A capa e a galeria de uma página, no formato que o site monta num `<img>`: endereços das
 * derivadas que existem agora, dimensões, texto alternativo e legenda da biblioteca.
 *
 * Roda dentro do cache de App\Actions\Content\ResolvePublicPageBySlug, pelo mesmo motivo de
 * App\Actions\Media\ExpandContentImages, e quem muda a imagem esquece esse cache
 * (ForgetPagesUsingMedia). O `sizes` não vem daqui: quanto da tela a imagem ocupa depende do
 * layout de cada página do site, que é quem sabe.
 *
 * Imagem impublicável (marcada como foto de assistido) não sai, e a galeria fecha o buraco.
 *
 * @phpstan-type PublicImage array{src: string, srcset: string, full: string, width: int, height: int, alt: string, caption: ?string, credit: ?string}
 */
final class BuildPublicPageImages
{
    /**
     * @return array{cover: PublicImage|null, gallery: list<PublicImage>}
     */
    public function handle(Page $page): array
    {
        $images = $page->images()->with('media')->get()
            ->filter(fn (PageImage $image): bool => $image->media->isPublishable());

        $cover = $images->first(fn (PageImage $image): bool => $image->role === PageImageRole::Cover);

        return [
            'cover' => $cover !== null ? $this->present($cover->media) : null,
            'gallery' => array_values($images
                ->filter(fn (PageImage $image): bool => $image->role === PageImageRole::Gallery)
                ->map(fn (PageImage $image): array => $this->present($image->media))
                ->all()),
        ];
    }

    /**
     * @return PublicImage
     */
    private function present(Media $media): array
    {
        return [
            'src' => MediaUrl::derivative($media->uuid, MediaVariants::pick($media->widths, MediaVariants::DEFAULT_WIDTH)),
            'srcset' => MediaUrl::srcset($media->uuid, $media->widths),
            // A maior derivada: o destino do link da imagem, que abre a ampliação no site e,
            // sem JavaScript, a própria foto grande.
            'full' => MediaUrl::derivative($media->uuid, MediaVariants::largest($media->widths)),
            'width' => $media->width,
            'height' => $media->height,
            'alt' => $media->alt,
            'caption' => $media->caption,
            'credit' => $media->credit,
        ];
    }
}
