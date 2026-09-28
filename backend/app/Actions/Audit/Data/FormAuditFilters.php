<?php

declare(strict_types=1);

namespace App\Actions\Audit\Data;

use App\Enums\FormSubmissionType;
use App\Models\User;

/**
 * Recorte pedido à tela de Auditoria. `$record` é o uuid de um formulário específico — é o que a
 * seção "Histórico de acessos" do detalhe usa, com os outros filtros vazios.
 */
final readonly class FormAuditFilters
{
    public function __construct(
        public ?FormSubmissionType $type = null,
        public ?User $causer = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $record = null,
        public int $perPage = 25,
    ) {}
}
