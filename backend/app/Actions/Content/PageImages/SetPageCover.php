<?php

declare(strict_types=1);

namespace App\Actions\Content\PageImages;

use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Models\User;
use App\Support\Cache\PublicPageCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Põe uma imagem da biblioteca na capa da página, no lugar da que houver. A capa anterior sai só
 * da capa: continua na biblioteca e, se estiver na galeria, continua lá.
 *
 * A capa aparece em OUTRAS páginas do site (App\Support\Content\CoverPlacements), que leem a
 * página desta capa pela API; esquecer o cache desta página basta para todas elas mudarem.
 */
final class SetPageCover
{
    public function __construct(private readonly PlaceImageOnPage $place) {}

    public function handle(Page $page, Media $media, ?User $actor): void
    {
        DB::transaction(function () use ($page, $media, $actor): void {
            $previous = PageImage::query()
                ->where('page_id', $page->id)
                ->where('role', PageImageRole::Cover)
                ->with('media')
                ->first()?->media;

            $this->place->handle($page, $media, PageImageRole::Cover);

            activity('media')
                ->causedBy($actor)
                ->performedOn($media)
                ->event('placed')
                ->withProperties([
                    'page' => $page->slug,
                    'role' => PageImageRole::Cover->value,
                    'replaced' => $previous?->uuid,
                ])
                ->log('Imagem posta na capa da página');
        });

        Cache::forget(PublicPageCache::key($page->slug));
    }
}
