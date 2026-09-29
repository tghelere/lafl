<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Media\Concerns\WritesMediaFiles;
use App\Actions\Media\Data\MediaDetailsData;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\ImageProcessor;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StoreMedia
{
    use WritesMediaFiles;

    public function __construct(private readonly ImageProcessor $processor) {}

    /**
     * Recusa foto declarada como de criança ou adolescente atendido. Não é uma trava de
     * interface: o sistema ainda não registra consentimento de imagem (a Fase 2 está bloqueada
     * até o inventário de dados — docs/roadmap.md), então não há consentimento vigente capaz
     * de tornar a foto publicável (CLAUDE.md, regra 6), e guardar a foto de um assistido sem
     * finalidade possível é tratamento de dado sem base. Ver
     * docs/decisoes/0024-biblioteca-de-midia.md.
     */
    public function handle(string $uploadedPath, MediaDetailsData $details, ?User $actor): Media
    {
        if ($details->depictsAssistedMinor) {
            throw ValidationException::withMessages([
                'depicts_assisted_minor' => [
                    'Fotos em que aparece alguém que hoje ainda é criança ou adolescente atendido pela instituição, '.
                    'ou que já foi atendido, ainda não podem ser cadastradas: elas exigem consentimento de imagem '.
                    'do responsável, que o sistema ainda não registra. Foto de acervo em que todas as pessoas '.
                    'retratadas já são adultas responde "Não".',
                ],
            ]);
        }

        // Fora da transação: é a parte cara (segundos numa foto grande) e não toca o banco.
        $image = $this->processor->process($uploadedPath);

        $media = new Media;
        $media->alt = $details->alt;
        $media->caption = $details->caption;
        $media->credit = $details->credit;
        $media->depicts_assisted_minor = false;
        $media->version = 1;
        $this->fillFromImage($media, $image);

        try {
            DB::transaction(function () use ($media, $image, $actor): void {
                $media->save();
                $this->writeFiles($media, 1, $image);

                activity('media')
                    ->causedBy($actor)
                    ->performedOn($media)
                    ->event('uploaded')
                    ->withProperties([
                        'alt' => $media->alt,
                        'width' => $media->width,
                        'height' => $media->height,
                        'size' => $media->size,
                    ])
                    ->log('Imagem enviada à biblioteca');
            });
        } catch (Throwable $e) {
            if ($media->uuid !== null) {
                Storage::disk('local')->deleteDirectory(MediaPaths::root($media));
            }

            throw $e;
        }

        return $media;
    }
}
