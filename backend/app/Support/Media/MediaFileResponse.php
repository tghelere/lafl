<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A resposta com o arquivo de uma imagem, comum à rota pública e à do painel.
 *
 * `$variant`: `null` (a largura padrão), `{largura}.webp`, ou `original` — este só no painel.
 *
 * Cache com revalidação, não `immutable`: o endereço é estável de propósito (substituir o
 * arquivo não o muda), então o navegador precisa voltar a perguntar. O ETag vem do SHA-256 da
 * original mais a largura: muda exatamente quando o arquivo muda, e a pergunta de volta custa
 * um 304 sem corpo.
 */
final class MediaFileResponse
{
    /** Dez minutos, o mesmo teto do cache do HTML das páginas (ResolvePublicPageBySlug). */
    private const PUBLIC_MAX_AGE = 600;

    public static function make(Request $request, Media $media, ?string $variant, bool $public): Response
    {
        if ($variant === 'original' && ! $public) {
            $path = MediaPaths::original($media);
            $tag = 'original';
            $mime = $media->mime;
        } elseif ($variant === null || preg_match('/^(\d{2,4})\.webp$/', $variant, $matches) === 1) {
            $requested = isset($matches[1]) ? (int) $matches[1] : MediaVariants::DEFAULT_WIDTH;
            $width = MediaVariants::pick($media->widths, $requested);
            $path = MediaPaths::derivative($media, $width);
            $tag = (string) $width;
            $mime = 'image/webp';
        } else {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($disk->path($path), Response::HTTP_OK, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ], public: false, autoEtag: false, autoLastModified: false);

        $response->setEtag($media->sha256.'-'.$tag);
        $response->setLastModified($media->updated_at);

        if ($public) {
            $response->setPublic();
            $response->setMaxAge(self::PUBLIC_MAX_AGE);
        } else {
            $response->setPrivate();
            $response->setMaxAge(0);
        }

        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setContentDisposition('inline', $media->uuid.'-'.$tag.'.'.($tag === 'original' ? $media->extension : 'webp'));
        $response->isNotModified($request);

        return $response;
    }
}
