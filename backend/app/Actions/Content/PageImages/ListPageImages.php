<?php

declare(strict_types=1);

namespace App\Actions\Content\PageImages;

use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Support\Media\MediaUrl;

/**
 * As imagens que uma página usa, para "Imagens desta página" no painel: capa, galeria em ordem
 * e as do meio do texto, na ordem em que aparecem no conteúdo SALVO.
 *
 * Inclui a imagem impublicável (marcada como foto de assistido): quem edita precisa vê-la para
 * tirá-la da página. O site já não a mostra.
 */
final class ListPageImages
{
    /**
     * @return array{cover: Media|null, gallery: list<Media>, content: list<Media>}
     */
    public function handle(Page $page): array
    {
        $placed = $page->images()->with('media')->get();

        preg_match_all(MediaUrl::CANONICAL_IN_HTML_PATTERN, $page->content, $matches);
        $inText = array_values(array_unique($matches[1]));
        $textMedia = Media::query()->whereIn('uuid', $inText)->get()->keyBy('uuid');

        return [
            'cover' => $placed->first(fn (PageImage $image): bool => $image->role === PageImageRole::Cover)?->media,
            'gallery' => array_values($placed
                ->filter(fn (PageImage $image): bool => $image->role === PageImageRole::Gallery)
                ->map(fn (PageImage $image): Media => $image->media)
                ->all()),
            // Imagem citada no texto que já não existe fica de fora: o site também não a mostra.
            'content' => array_values(array_filter(array_map(
                fn (string $uuid): ?Media => $textMedia->get($uuid),
                $inText,
            ))),
        ];
    }
}
