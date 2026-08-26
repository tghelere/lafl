<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;

/**
 * Única exceção da matriz "formulários recebidos" (ver docs/estrutura-site.md §4.4):
 * `bazar` só acessa `pickup_requests`, porque o pedido de coleta carrega endereço
 * residencial do doador e a equipe do bazar tende a ser própria e rotativa.
 */
final class PickupRequestPolicy extends FormSubmissionPolicy
{
    protected function allowedRoles(): array
    {
        return [...parent::allowedRoles(), Role::Bazar->value];
    }
}
