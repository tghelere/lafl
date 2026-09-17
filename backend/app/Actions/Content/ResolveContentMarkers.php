<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentMarker;
use App\Services\InstitutionalFacts;

/**
 * Troca os marcadores escritos no conteúdo de uma página pelo valor calculado agora (ver
 * App\Enums\ContentMarker).
 *
 * Roda SÓ na leitura pública, e SÓ DEPOIS do cache de App\Actions\Content\
 * ResolvePublicPageBySlug — as duas coisas por motivos diferentes e igualmente obrigatórias:
 *
 * - depois do cache, porque o cache guarda o conteúdo cru, por dez minutos. Se o valor fosse
 *   resolvido antes, publicar um documento só mudaria a contagem no site quando aquele cache
 *   expirasse;
 * - só no público, porque o endpoint administrativo devolve o marcador cru. Se o painel
 *   recebesse "73 anos", o primeiro salvamento gravaria esse texto no banco e o cálculo
 *   morreria em silêncio — a página seguiria dizendo 73 para sempre.
 *
 * Marcador desconhecido fica como está, cru. Ele não deveria existir (App\Actions\Content\
 * AssertContentMarkersAreKnown recusa ao salvar), e se um dia existir é melhor que apareça
 * do que sumir com o trecho.
 */
final class ResolveContentMarkers
{
    public function __construct(private readonly InstitutionalFacts $facts) {}

    public function handle(string $content): string
    {
        if (! str_contains($content, '{{')) {
            // Atalho deliberado: a maioria das páginas não tem marcador nenhum, e sem ele
            // toda leitura pública pagaria a contagem de documentos no banco à toa.
            return $content;
        }

        return (string) preg_replace_callback(
            ContentMarker::PATTERN,
            function (array $matches): string {
                $marker = ContentMarker::tryFrom($matches[1]);

                return $marker !== null ? $this->facts->value($marker) : $matches[0];
            },
            $content,
        );
    }
}
