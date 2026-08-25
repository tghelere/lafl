<?php

declare(strict_types=1);

namespace App\Actions\Transparency;

use App\Models\TransparencyDocument;

final class DeleteTransparencyDocument
{
    /**
     * Soft delete — o arquivo permanece no disco (ver SaveTransparencyDocument), só a
     * publicação some das listagens.
     */
    public function handle(TransparencyDocument $document): void
    {
        $document->delete();
    }
}
