<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Support\Media\MediaUrl;
use App\Support\Media\MediaVariants;
use Illuminate\Support\Collection;

/**
 * Transforma o `<img src="/midia/{uuid}" alt="…">` gravado no conteúdo no `<img>` completo que
 * o site entrega: `srcset` com as derivadas que existem AGORA, `sizes`, `width`/`height` da
 * proporção atual (sem deslocar o layout enquanto carrega) e carregamento preguiçoso.
 *
 * Roda na leitura pública, dentro do cache de App\Actions\Content\ResolvePublicPageBySlug —
 * quem invalida esse cache quando a imagem muda é App\Actions\Media\ForgetPagesUsingMedia. O
 * endpoint administrativo devolve a forma gravada, crua: se o painel recebesse o `srcset`,
 * o primeiro salvamento o gravaria, e a substituição do arquivo deixaria de refletir.
 *
 * Imagem que não existe mais, ou que deixou de ser publicável, sai da saída — a `<figure>`
 * inteira, com a legenda, que sem a imagem descreveria o nada. É a segunda barreira: a
 * primeira é a recusa ao salvar (AssertContentImagesArePublishable), e esta cobre o que muda
 * DEPOIS de a página ser salva.
 *
 * As expressões regulares rodam sobre HTML sanitizado no backend (ver
 * App\Support\Html\ContentSanitizer): atributo com `>` ou aspas vem escapado, e `<figure>` não
 * se aninha (não existe forma de o editor ou o filtro produzir isso).
 */
final class ExpandContentImages
{
    /**
     * A coluna de texto do site tem 68ch (frontend-site, `.prose`), ~42rem na fonte de corpo.
     * Abaixo de 48rem a imagem ocupa a largura da tela menos as margens.
     */
    private const SIZES = '(min-width: 48rem) 42rem, calc(100vw - 2rem)';

    public function handle(string $content): string
    {
        if (preg_match_all(MediaUrl::CANONICAL_IN_HTML_PATTERN, $content, $matches) === 0) {
            return $content;
        }

        /** @var Collection<string, Media> $media */
        $media = Media::query()
            ->whereIn('uuid', array_unique($matches[1]))
            ->get()
            ->filter(fn (Media $item): bool => $item->isPublishable())
            ->keyBy('uuid');

        $content = (string) preg_replace_callback(
            '#<figure>.*?</figure>#s',
            fn (array $figure): string => $this->referencesUnavailable($figure[0], $media) ? '' : $figure[0],
            $content,
        );

        return (string) preg_replace_callback(
            '/<img\b[^>]*>/i',
            fn (array $img): string => $this->expand($img[0], $media),
            $content,
        );
    }

    /**
     * @param  Collection<string, Media>  $media
     */
    private function referencesUnavailable(string $html, Collection $media): bool
    {
        preg_match_all(MediaUrl::CANONICAL_IN_HTML_PATTERN, $html, $matches);

        foreach ($matches[1] as $uuid) {
            if (! $media->has($uuid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<string, Media>  $media
     */
    private function expand(string $tag, Collection $media): string
    {
        if (preg_match(MediaUrl::CANONICAL_IN_HTML_PATTERN, $tag, $src) !== 1) {
            return $tag;
        }

        $item = $media->get($src[1]);

        if ($item === null) {
            return '';
        }

        preg_match('/\salt="([^"]*)"/', $tag, $alt);

        $srcset = implode(', ', array_map(
            static fn (int $width): string => MediaUrl::derivative($item->uuid, $width).' '.$width.'w',
            $item->widths,
        ));
        $fallback = MediaUrl::derivative($item->uuid, MediaVariants::pick($item->widths, MediaVariants::DEFAULT_WIDTH));

        return sprintf(
            '<img src="%s" srcset="%s" sizes="%s" width="%d" height="%d" alt="%s" loading="lazy" decoding="async" />',
            $fallback,
            $srcset,
            self::SIZES,
            $item->width,
            $item->height,
            $alt[1] ?? '',
        );
    }
}
