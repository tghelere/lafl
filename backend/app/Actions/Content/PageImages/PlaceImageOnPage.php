<?php

declare(strict_types=1);

namespace App\Actions\Content\PageImages;

use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use Illuminate\Validation\ValidationException;

/**
 * Põe uma imagem da biblioteca na capa ou no fim da galeria de uma página.
 *
 * Roda DENTRO da transação de quem chama: é peça de App\Actions\Media\ImportInitialPhotos e de
 * App\Actions\Content\PageImages\UploadImageToPage, que criam a imagem e a ligação juntas. A
 * linha da página é travada antes de calcular a próxima posição, para que dois envios ao mesmo
 * tempo não disputem o mesmo lugar (a restrição única (page_id, role, position) recusaria o
 * segundo com erro de banco).
 *
 * A capa é uma só: pôr outra imagem na capa troca a imagem da capa.
 */
final class PlaceImageOnPage
{
    public function handle(Page $page, Media $media, PageImageRole $role): PageImage
    {
        if (! $media->isPublishable()) {
            throw ValidationException::withMessages([
                'media' => ['Esta imagem foi marcada como foto de criança ou adolescente atendido e não pode ir para o site.'],
            ]);
        }

        Page::query()->whereKey($page->id)->lockForUpdate()->first();

        if ($role === PageImageRole::Cover) {
            $image = PageImage::query()->where('page_id', $page->id)->where('role', $role)->first() ?? new PageImage;
            $image->page_id = $page->id;
            $image->role = $role;
            $image->position = 0;
            $image->media_id = $media->id;
            $image->save();

            return $image;
        }

        $alreadyThere = PageImage::query()
            ->where('page_id', $page->id)
            ->where('role', $role)
            ->where('media_id', $media->id)
            ->exists();

        if ($alreadyThere) {
            throw ValidationException::withMessages([
                'media' => ['Esta imagem já está na galeria desta página.'],
            ]);
        }

        $next = PageImage::query()->where('page_id', $page->id)->where('role', $role)->max('position');

        $image = new PageImage;
        $image->page_id = $page->id;
        $image->media_id = $media->id;
        $image->role = $role;
        $image->position = $next === null ? 0 : ((int) $next) + 1;
        $image->save();

        return $image;
    }
}
