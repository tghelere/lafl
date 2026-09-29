<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Content\PageImages\PlaceImageOnPage;
use App\Actions\Media\Concerns\WritesMediaFiles;
use App\Enums\PageImageRole;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageImage;
use App\Services\Media\ImageProcessor;
use App\Support\Cache\PublicPageCache;
use App\Support\Media\InitialPhotos;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Carrega na biblioteca as fotos de App\Support\Media\InitialPhotos e as põe na capa ou na
 * galeria das páginas.
 *
 * **Idempotente e não destrutivo**, como `conteudo:importar-inicial`: a foto cuja chave já
 * está em `media.origin_key` é pulada inteira, com os lugares dela. Rodar de novo não duplica
 * nada, e não pisa no que a equipe fez depois: a foto substituída, o texto alternativo
 * corrigido e a ordem mudada ficam como estão.
 *
 * Duas situações em que a foto não entra, e que o resultado lista:
 * - a página de destino não existe (ou está na lixeira): nada da foto é gravado, para que ela
 *   não fique na biblioteca sem o lugar que devia ter. Rodar `conteudo:importar-inicial` antes
 *   e repetir este comando resolve;
 * - o arquivo não está em `resources/initial-photos/`: falha alta, porque é defeito do pacote.
 *
 * A capa já ocupada por outra imagem não é trocada. A foto entra na biblioteca e na galeria, e
 * o resultado avisa.
 *
 * Cada foto é uma transação própria. Uma falha no meio deixa as anteriores gravadas, e a
 * próxima execução continua de onde parou.
 */
final class ImportInitialPhotos
{
    use WritesMediaFiles;

    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly PlaceImageOnPage $place,
    ) {}

    /**
     * @return array{created: list<string>, skipped: list<string>, missing_page: array<string, string>, cover_kept: array<string, string>}
     */
    public function handle(): array
    {
        $result = ['created' => [], 'skipped' => [], 'missing_page' => [], 'cover_kept' => []];
        $touchedSlugs = [];

        foreach (InitialPhotos::all() as $key => $photo) {
            if (Media::query()->where('origin_key', $key)->exists()) {
                $result['skipped'][] = $key;

                continue;
            }

            $pages = [];
            foreach ($photo['places'] as $place) {
                $page = Page::query()->where('slug', $place['page'])->first();

                if ($page === null) {
                    $result['missing_page'][$key] = $place['page'];

                    continue 2;
                }

                $pages[$place['page']] = $page;
            }

            $source = InitialPhotos::path($key);

            if (! is_file($source)) {
                throw new RuntimeException("Arquivo da foto inicial {$key} não encontrado: {$source}");
            }

            $image = $this->processor->process($source);

            $media = new Media;
            $media->origin_key = $key;
            $media->alt = $photo['alt'];
            $media->caption = $photo['caption'];
            $media->depicts_assisted_minor = false;
            $media->version = 1;
            $this->fillFromImage($media, $image);

            try {
                DB::transaction(function () use ($media, $image, $photo, $pages, $key, &$result): void {
                    $media->save();
                    $this->writeFiles($media, 1, $image);

                    foreach ($photo['places'] as $place) {
                        $page = $pages[$place['page']];

                        if ($place['role'] === PageImageRole::Cover
                            && PageImage::query()->where('page_id', $page->id)->where('role', PageImageRole::Cover)->exists()) {
                            $result['cover_kept'][$key] = $place['page'];

                            continue;
                        }

                        $this->place->handle($page, $media, $place['role']);
                    }

                    activity('media')
                        ->performedOn($media)
                        ->event('imported')
                        ->withProperties(['origin_key' => $key, 'alt' => $media->alt, 'width' => $media->width, 'height' => $media->height])
                        ->log('Foto inicial do site importada para a biblioteca');
                });
            } catch (Throwable $e) {
                if ($media->uuid !== null) {
                    Storage::disk('local')->deleteDirectory(MediaPaths::root($media));
                }

                throw $e;
            }

            $result['created'][] = $key;
            $touchedSlugs = [...$touchedSlugs, ...array_keys($pages)];
        }

        foreach (array_unique($touchedSlugs) as $slug) {
            Cache::forget(PublicPageCache::key($slug));
        }

        return $result;
    }
}
