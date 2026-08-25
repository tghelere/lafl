<?php

declare(strict_types=1);

namespace App\Actions\Transparency;

use App\Models\TransparencyDocument;

final class RegisterTransparencyDocumentDownload
{
    /**
     * Soma a contagem antes de servir o arquivo — é por isso que o download passa pela API
     * em vez de ser um link direto ao disco (ver docs/estrutura-site.md §3.1).
     */
    public function handle(TransparencyDocument $document): void
    {
        $document->increment('download_count');
    }
}
