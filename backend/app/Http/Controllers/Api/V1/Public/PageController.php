<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Content\ListPublicPages;
use App\Actions\Content\ResolveContentMarkers;
use App\Actions\Content\ResolvePublicPageBySlug;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PageListItemResource;
use App\Http\Resources\Public\PageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PageController extends Controller
{
    /**
     * Inventário das páginas publicadas (slug e data de alteração), para o sitemap do site —
     * ver frontend-site/server/routes/sitemap.xml.ts. Sem conteúdo e sem título: é listagem
     * de endereços, não de texto.
     */
    public function index(Request $request, ListPublicPages $action): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 50), 100);

        return PageListItemResource::collection($action->handle($perPage));
    }

    public function show(
        string $slug,
        ResolvePublicPageBySlug $resolver,
        ResolveContentMarkers $markers,
    ): HttpResponse {
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

        $hasMarkers = str_contains($result['content'], '{{');

        // Depois do cache de ResolvePublicPageBySlug, de propósito: o cache guarda o conteúdo
        // cru por dez minutos, e um número calculado preso a essa janela é o bug que os
        // marcadores existem para não ter (ver App\Actions\Content\ResolveContentMarkers).
        $result['content'] = $markers->handle($result['content']);

        /** @var Response $response */
        $response = (new PageResource((object) $result))->response();

        return $response
            // Página com marcador muda sem a página ter sido editada — publicar um documento
            // altera a contagem. Guardar essa resposta por tempo devolveria o número velho a
            // quem pedisse de novo, então cada requisição revalida; o ETag, que inclui o
            // conteúdo já resolvido, é quem responde 304 enquanto o número não mudar.
            ->setCache($hasMarkers
                ? ['public' => true, 'max_age' => 0, 'must_revalidate' => true]
                : ['public' => true, 'max_age' => 300])
            ->setEtag(md5($result['updated_at'].'|'.$result['slug'].'|'.$result['content']));
    }
}
