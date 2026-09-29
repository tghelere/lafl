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
 * Troca a foto de lugar com a vizinha de cima ou de baixo na galeria. A ordem é a que o site
 * mostra e a que viaja no pacote de conteúdo (`position`).
 *
 * A troca passa por uma posição temporária (a maior + 1) porque a restrição única
 * (page_id, role, position) recusaria as duas fotos na mesma posição, mesmo por um instante,
 * dentro da transação. A linha da página é travada antes, como em PlaceImageOnPage, para que
 * duas pessoas movendo ao mesmo tempo não se cruzem.
 */
final class MoveGalleryImage
{
    /**
     * @return array{position: int, count: int} a posição nova (a partir de 1) e o total
     */
    public function handle(Page $page, Media $media, string $direction, ?User $actor): array
    {
        $result = DB::transaction(function () use ($page, $media, $direction, $actor): array {
            Page::query()->whereKey($page->id)->lockForUpdate()->first();

            $gallery = PageImage::query()->where('page_id', $page->id)->where('role', PageImageRole::Gallery);
            $moving = (clone $gallery)->where('media_id', $media->id)->first();

            if ($moving === null) {
                throw ValidationException::withMessages(['media' => ['Esta imagem não está na galeria desta página.']]);
            }

            $from = $moving->position;
            $neighbor = (clone $gallery)
                ->where('position', $direction === 'up' ? '<' : '>', $from)
                ->orderBy('position', $direction === 'up' ? 'desc' : 'asc')
                ->first();

            if ($neighbor === null) {
                throw ValidationException::withMessages(['direction' => [
                    $direction === 'up' ? 'Esta imagem já é a primeira da galeria.' : 'Esta imagem já é a última da galeria.',
                ]]);
            }

            $to = $neighbor->position;

            $moving->position = ((int) (clone $gallery)->max('position')) + 1;
            $moving->save();
            $neighbor->position = $from;
            $neighbor->save();
            $moving->position = $to;
            $moving->save();

            $count = (clone $gallery)->count();
            $rank = (clone $gallery)->where('position', '<=', $to)->count();

            activity('media')
                ->causedBy($actor)
                ->performedOn($media)
                ->event('moved')
                ->withProperties(['page' => $page->slug, 'from' => $from, 'to' => $to])
                ->log($direction === 'up' ? 'Imagem movida para cima na galeria' : 'Imagem movida para baixo na galeria');

            return ['position' => $rank, 'count' => $count];
        });

        Cache::forget(PublicPageCache::key($page->slug));

        return $result;
    }
}
