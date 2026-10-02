<?php

declare(strict_types=1);

namespace App\Actions\Partners;

use App\Models\Partner;

final class DeletePartner
{
    /**
     * Soft delete, como nos outros cadastros: sai da página e das listagens, e o registro
     * continua no banco. A logo permanece na biblioteca.
     */
    public function handle(Partner $partner): void
    {
        $partner->delete();
    }
}
