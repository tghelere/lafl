<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Media\ResolveLegacyPhotoUrl;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Media\MediaFileResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MediaController extends Controller
{
    /**
     * A imagem para o SITE. Quem chama é a rota de proxy do Nitro
     * (frontend-site/server/routes/midia/), que espelha este caminho — o visitante vê
     * `/midia/{uuid}/{largura}.webp` no domínio do site.
     *
     * Imagem impublicável responde 404, e não 403: para o público ela não existe. É o que tira
     * do ar, na hora, a foto marcada como de assistido depois de inserida numa página — mesmo
     * que o HTML antigo ainda esteja em algum cache.
     *
     * A original nunca é servida aqui: o site só precisa das derivadas.
     */
    public function file(Request $request, string $uuid, ?string $variant = null): Response
    {
        $media = Media::query()->where('uuid', $uuid)->first();

        if ($media === null || ! $media->isPublishable() || $variant === 'original') {
            abort(Response::HTTP_NOT_FOUND);
        }

        return MediaFileResponse::make($request, $media, $variant, public: true);
    }

    /**
     * Endereço antigo de foto do site (`/fotos/{secao}/{chave}-{largura}.{webp,jpg}`, arquivos
     * fixos até a sessão 28). Só 301 para o endereço de agora, para não perder o que foi
     * indexado. Quem chama é a rota do Nitro em frontend-site/server/routes/fotos/, que repassa o
     * 301 ao visitante.
     */
    public function legacy(string $section, string $file, ResolveLegacyPhotoUrl $action): Response
    {
        $location = $action->handle($section, $file);

        if ($location === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return redirect()->away($location, Response::HTTP_MOVED_PERMANENTLY);
    }
}
