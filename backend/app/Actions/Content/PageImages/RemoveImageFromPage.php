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
use Illuminate\Validation\ValidationException;

/**
 * Tira a imagem da capa ou da galeria da página. Só a ligação: a imagem continua na biblioteca,
 * e excluí-la de vez é outro ato, com outra permissão (DeleteMedia, só `direcao`).
 *
 * Na galeria, as fotos seguintes sobem uma posição, em ordem crescente, para não colidir com a
 * restrição única (page_id, role, position). A linha da página é travada antes, como em
 * PlaceImageOnPage.
 */
final class RemoveImageFromPage
{
    public function handle(Page $page, Media $media, PageImageRole $role, ?User $actor): void
    {
        DB::transaction(function () use ($page, $media, $role, $actor): void {
            Page::query()->whereKey($page->id)->lockForUpdate()->first();

            $image = PageImage::query()
                ->where('page_id', $page->id)
                ->where('role', $role)
                ->where('media_id', $media->id)
                ->first();

            if ($image === null) {
                throw ValidationException::withMessages([
                    'media' => [$role === PageImageRole::Cover
                        ? 'Esta imagem não é a capa desta página.'
                        : 'Esta imagem não está na galeria desta página.'],
                ]);
            }

            $image->delete();

            $following = PageImage::query()
                ->where('page_id', $page->id)
                ->where('role', $role)
                ->where('position', '>', $image->position)
                ->orderBy('position')
                ->get();

            foreach ($following as $next) {
                $next->position--;
                $next->save();
            }

            activity('media')
                ->causedBy($actor)
                ->performedOn($media)
                ->event('removed_from_page')
                ->withProperties(['page' => $page->slug, 'role' => $role->value])
                ->log($role === PageImageRole::Cover ? 'Imagem tirada da capa da página' : 'Imagem tirada da galeria da página');
        });

        Cache::forget(PublicPageCache::key($page->slug));
    }
}
