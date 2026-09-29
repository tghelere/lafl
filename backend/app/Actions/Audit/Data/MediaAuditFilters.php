<?php

declare(strict_types=1);

namespace App\Actions\Audit\Data;

use App\Enums\MediaAuditEvent;
use App\Models\User;

/** Recorte pedido à aba "Imagens" da Auditoria. */
final readonly class MediaAuditFilters
{
    public function __construct(
        public ?MediaAuditEvent $event = null,
        public ?User $causer = null,
        public ?string $from = null,
        public ?string $to = null,
        public int $perPage = 25,
    ) {}
}
