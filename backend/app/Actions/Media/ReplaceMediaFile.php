<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Media\Concerns\WritesMediaFiles;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\ImageProcessor;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Troca o arquivo mantendo a identidade — o mesmo uuid, logo o mesmo endereço público e o
 * mesmo `src` em todas as páginas que usam a imagem. Nenhuma página precisa ser editada.
 *
 * Os arquivos novos vão para a pasta da versão seguinte; a versão só muda no banco dentro da
 * transação, com a linha travada (duas substituições simultâneas não gravam na mesma pasta);
 * a pasta anterior só é apagada depois do commit. Em nenhum instante o endereço público
 * aponta para arquivo inexistente.
 *
 * A versão anterior é apagada, não guardada: substituir é, muitas vezes, corrigir uma foto
 * que não devia estar no ar — e uma cópia escondida no disco não cumpriria isso.
 */
final class ReplaceMediaFile
{
    use WritesMediaFiles;

    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly ForgetPagesUsingMedia $forgetPages,
    ) {}

    public function handle(Media $media, string $uploadedPath, ?User $actor): Media
    {
        $image = $this->processor->process($uploadedPath);
        $disk = Storage::disk('local');

        $previousVersion = null;
        $newVersion = null;

        try {
            DB::transaction(function () use ($media, $image, $actor, &$previousVersion, &$newVersion): void {
                $locked = Media::query()->whereKey($media->getKey())->lockForUpdate()->firstOrFail();

                $previousVersion = $locked->version;
                $newVersion = $locked->version + 1;
                $before = ['width' => $locked->width, 'height' => $locked->height, 'size' => $locked->size, 'sha256' => $locked->sha256];

                $this->writeFiles($media, $newVersion, $image);

                $media->version = $newVersion;
                $this->fillFromImage($media, $image);
                $media->save();

                activity('media')
                    ->causedBy($actor)
                    ->performedOn($media)
                    ->event('replaced')
                    ->withProperties([
                        'old' => $before,
                        'attributes' => ['width' => $media->width, 'height' => $media->height, 'size' => $media->size, 'sha256' => $media->sha256],
                    ])
                    ->log('Arquivo da imagem substituído');
            });
        } catch (Throwable $e) {
            if ($newVersion !== null) {
                $disk->deleteDirectory(MediaPaths::directory($media, $newVersion));
            }

            throw $e;
        }

        if ($previousVersion !== null) {
            $disk->deleteDirectory(MediaPaths::directory($media, $previousVersion));
        }

        $this->forgetPages->handle($media);

        return $media;
    }
}
