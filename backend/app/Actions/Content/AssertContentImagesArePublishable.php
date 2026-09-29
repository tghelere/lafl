<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Media;
use App\Support\Media\MediaUrl;
use Illuminate\Validation\ValidationException;

/**
 * Recusa o salvamento de uma página cujo conteúdo (já sanitizado) traga imagem que não pode ir
 * para o site: que não existe mais na biblioteca, ou que foi marcada como foto de criança ou
 * adolescente atendido (CLAUDE.md, regra 6 — travada aqui, na API, e não no seletor do
 * editor). Também recusa imagem sem texto alternativo: `alt` é obrigatório em toda imagem
 * (docs/arquitetura.md, SEO).
 *
 * Mesma lógica de AssertContentMarkersAreKnown: na escrita existe alguém a quem avisar. A
 * leitura pública também se protege (App\Actions\Media\ExpandContentImages retira a imagem
 * que deixou de ser publicável), mas em silêncio — é aqui que a pessoa fica sabendo.
 */
final class AssertContentImagesArePublishable
{
    public function handle(string $content): void
    {
        if (preg_match_all('/<img\b[^>]*>/i', $content, $tags) === 0) {
            return;
        }

        $uuids = [];
        foreach ($tags[0] as $tag) {
            if (preg_match('/\salt="\s*"/', $tag) === 1 || preg_match('/\salt="/', $tag) !== 1) {
                throw ValidationException::withMessages([
                    'content' => ['Toda imagem do conteúdo precisa de texto alternativo. Edite a imagem e descreva o que ela mostra.'],
                ]);
            }

            if (preg_match(MediaUrl::CANONICAL_IN_HTML_PATTERN, $tag, $match) === 1) {
                $uuids[] = $match[1];
            }
        }

        $uuids = array_values(array_unique($uuids));
        $media = Media::query()->whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $missing = array_filter($uuids, static fn (string $uuid): bool => ! $media->has($uuid));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'content' => [
                    count($missing) === 1
                        ? 'O conteúdo usa uma imagem que não existe mais na biblioteca. Remova-a e insira outra.'
                        : 'O conteúdo usa imagens que não existem mais na biblioteca. Remova-as e insira outras.',
                ],
            ]);
        }

        $blocked = $media->reject(fn (Media $item): bool => $item->isPublishable());

        if ($blocked->isNotEmpty()) {
            $names = $blocked->map(fn (Media $item): string => '"'.$item->alt.'"')->implode(', ');

            throw ValidationException::withMessages([
                'content' => [
                    "A imagem {$names} foi marcada como foto de criança ou adolescente atendido e não pode ir para o site. Remova-a do conteúdo.",
                ],
            ]);
        }
    }
}
