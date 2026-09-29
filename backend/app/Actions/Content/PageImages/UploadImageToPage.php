<?php

declare(strict_types=1);

namespace App\Actions\Content\PageImages;

use App\Actions\Media\Data\MediaDetailsData;
use App\Actions\Media\StoreMedia;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Support\Cache\PublicPageCache;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Enviar" em "Imagens desta página": a foto entra na biblioteca (o mesmo StoreMedia do envio
 * pela tela de imagens, com a mesma recusa de foto de assistido e a mesma auditoria) e vai para
 * o fim da galeria da página.
 *
 * As duas coisas numa transação: se a ligação falhar, a imagem não fica na biblioteca sem o
 * lugar que a pessoa pediu, e os arquivos já gravados são apagados.
 */
final class UploadImageToPage
{
    public function __construct(
        private readonly StoreMedia $store,
        private readonly PlaceImageOnPage $place,
    ) {}

    public function handle(Page $page, string $uploadedPath, MediaDetailsData $details, ?User $actor): Media
    {
        // Por referência: se a ligação falhar DEPOIS de StoreMedia gravar os arquivos, é por aqui
        // que o catch sabe qual pasta apagar (o rollback desfaz só o banco).
        $stored = null;

        try {
            $media = DB::transaction(function () use ($page, $uploadedPath, $details, $actor, &$stored): Media {
                $media = $stored = $this->store->handle($uploadedPath, $details, $actor);
                $this->place->handle($page, $media, PageImageRole::Gallery);

                activity('media')
                    ->causedBy($actor)
                    ->performedOn($media)
                    ->event('placed')
                    ->withProperties(['page' => $page->slug, 'role' => PageImageRole::Gallery->value])
                    ->log('Imagem posta na galeria da página');

                return $media;
            });
        } catch (Throwable $e) {
            if ($stored instanceof Media) {
                Storage::disk('local')->deleteDirectory(MediaPaths::root($stored));
            }

            throw $e;
        }

        Cache::forget(PublicPageCache::key($page->slug));

        return $media;
    }
}
