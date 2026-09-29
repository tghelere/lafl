<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Support\Media\InitialPhotos;
use App\Support\Media\MediaUrl;
use App\Support\Media\MediaVariants;

/**
 * O endereço de agora para um endereço antigo de foto do site,
 * `/fotos/{secao}/{chave}-{largura}.{webp,jpg}`, que existia até a sessão 28 (ver
 * docs/decisoes/0025-imagens-da-pagina.md). O site responde 301 para o que vier daqui, e o que
 * um buscador indexou continua levando à mesma foto.
 *
 * Só reconhece a combinação exata de seção e chave do catálogo inicial (InitialPhotos), e só
 * redireciona para imagem que ainda existe e pode ir para o site: foto marcada como de
 * assistido, ou excluída, dá 404, como no endereço novo. A largura pedida cai na maior derivada
 * que cabe (MediaVariants::pick), e o `.jpg` antigo vai para o `.webp`, que é o que existe.
 */
final class ResolveLegacyPhotoUrl
{
    private const FILE_PATTERN = '/^([a-z0-9-]+)-(\d{2,4})\.(?:webp|jpg)$/';

    public function handle(string $section, string $file): ?string
    {
        if (preg_match(self::FILE_PATTERN, $file, $match) !== 1) {
            return null;
        }

        [, $key, $width] = $match;
        $photo = InitialPhotos::all()[$key] ?? null;

        if ($photo === null || $photo['section'] !== $section) {
            return null;
        }

        $media = Media::query()->where('origin_key', $key)->first();

        if ($media === null || ! $media->isPublishable()) {
            return null;
        }

        // Absoluto, no domínio do site, como o 301 do PDF de transparência
        // (App\Support\Transparency\DocumentUrl::absolute): é o endereço que o buscador guarda.
        return rtrim((string) config('forms.site_base_url'), '/')
            .MediaUrl::derivative($media->uuid, MediaVariants::pick($media->widths, (int) $width));
    }
}
