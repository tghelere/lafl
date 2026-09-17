<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\InstitutionFactsResource;
use App\Services\InstitutionalFacts;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class InstitutionFactsController extends Controller
{
    public function show(InstitutionalFacts $facts): HttpResponse
    {
        /** @var Response $response */
        $response = (new InstitutionFactsResource($facts))->response();

        return $response
            // Mesmo raciocínio do conteúdo com marcador (ver Public\PageController): a
            // contagem muda sem ninguém editar nada, então a resposta revalida a cada
            // requisição e o ETag do próprio corpo é quem devolve 304 enquanto nada mudou.
            ->setCache(['public' => true, 'max_age' => 0, 'must_revalidate' => true])
            ->setEtag(md5((string) $response->getContent()));
    }
}
