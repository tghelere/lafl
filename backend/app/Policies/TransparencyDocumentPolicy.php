<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\TransparencyDocument;
use App\Models\User;

/**
 * super_admin não aparece aqui — bypass via Gate::before em AppServiceProvider (ver
 * docs/dominio.md, seção "Papéis").
 *
 * `direcao` e `financeiro` administram documentos de transparência; nem `comunicacao` nem
 * `atendimento` — publicar prestação de contas é ato de direção/financeiro, não de conteúdo
 * geral.
 */
final class TransparencyDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::Direcao->value, Role::Financeiro->value]);
    }

    public function view(User $user, TransparencyDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TransparencyDocument $document): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, TransparencyDocument $document): bool
    {
        return $this->viewAny($user);
    }
}
