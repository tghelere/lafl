<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Exclui a imagem e os arquivos de todas as versões — em definitivo, sem lixeira (ver o
 * docblock de App\Models\Media).
 *
 * Recusada enquanto alguma página usar a imagem, e a recusa diz quais: apagar em silêncio
 * deixaria um buraco no meio do texto publicado. Quem quer tirar a imagem do site tira da
 * página primeiro — é o ato que a pessoa enxerga e pode conferir.
 */
final class DeleteMedia
{
    public function __construct(private readonly FindMediaUsages $usages) {}

    public function handle(Media $media, ?User $actor): void
    {
        $usages = $this->usages->handle($media);

        if ($usages !== []) {
            $where = implode(', ', array_map(
                static fn (array $page): string => "{$page['title']} (/{$page['slug']})",
                $usages,
            ));

            throw ValidationException::withMessages([
                'media' => [
                    (count($usages) === 1 ? 'Esta imagem está em uso na página ' : 'Esta imagem está em uso nas páginas ').
                    "{$where}. Remova-a do conteúdo antes de excluir.",
                ],
            ]);
        }

        DB::transaction(function () use ($media, $actor): void {
            // Registrado ANTES de apagar, com o que identifica a imagem para quem ler o log
            // depois — a linha em `media` deixa de existir.
            activity('media')
                ->causedBy($actor)
                ->performedOn($media)
                ->event('deleted')
                ->withProperties(['uuid' => $media->uuid, 'alt' => $media->alt])
                ->log('Imagem excluída da biblioteca');

            $media->delete();
        });

        Storage::disk('local')->deleteDirectory(MediaPaths::root($media));
    }
}
