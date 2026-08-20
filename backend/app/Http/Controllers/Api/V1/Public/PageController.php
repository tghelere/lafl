<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Content\ResolvePublicPageBySlug;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PageResource;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PageController extends Controller
{
    public function show(string $slug, ResolvePublicPageBySlug $resolver): HttpResponse
    {
        $result = $resolver->handle($slug);

        if ($result === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        if (isset($result['redirect_to'])) {
            // 200 com corpo simples, não 301 real — este é um hop de servidor a servidor
            // (Nuxt chamando a API). Um 301 de verdade, visível ao navegador/buscador, é
            // responsabilidade do frontend, que decide a partir deste campo; um redirect
            // HTTP real aqui seria seguido de forma transparente por qualquer cliente fetch
            // (ofetch/undici), escondendo justamente a informação que o front precisa
            // repassar ao visitante.
            return response()->json(['redirect_to' => $result['redirect_to']]);
        }

        /** @var Response $response */
        $response = (new PageResource((object) $result))->response();

        return $response
            ->setCache(['public' => true, 'max_age' => 300])
            ->setEtag(md5($result['updated_at'].'|'.$result['slug']));
    }
}
