<?php

declare(strict_types=1);

namespace App\Actions\Transparency;

use App\Models\TransparencyDocument;
use App\Support\Http\KnownBots;

final class RegisterTransparencyDocumentDownload
{
    /**
     * Soma a contagem antes de servir o arquivo — é por isso que o PDF passa pela API em vez
     * de ser um link direto ao disco (ver docs/estrutura-site.md §3.1).
     *
     * **A contagem é aproximação, e a instituição precisa lê-la assim.** Duas razões, as duas
     * insolúveis sem custo desproporcional:
     *
     * - robô é reconhecido por User-Agent (ver App\Support\Http\KnownBots), texto que o
     *   cliente escolhe — robô disfarçado de navegador continua somando;
     * - um acesso não é uma pessoa: cada recarga da página do PDF soma de novo, e o navegador
     *   pode servir o arquivo do próprio cache sem chegar até aqui, que soma de menos.
     *
     * O User-Agent que interessa é o do VISITANTE, não o do servidor Nitro que faz proxy —
     * por isso a rota de PDF do site repassa o cabeçalho original (ver
     * frontend-site/server/routes/transparencia/documentos/[year]/[slug].get.ts).
     */
    public function handle(TransparencyDocument $document, ?string $userAgent): void
    {
        if (KnownBots::matches($userAgent)) {
            return;
        }

        $document->increment('download_count');
    }
}
