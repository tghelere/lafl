<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\TransparencyDocument;
use App\Models\User;

/**
 * super_admin não aparece aqui — bypass via Gate::before em AppServiceProvider (ver
 * docs/estrutura-site.md §4.4).
 *
 * Diferente de PagePolicy: só `direcao` administra documentos de transparência, nem
 * `comunicacao` nem `atendimento` — publicar prestação de contas é ato de direção, não de
 * conteúdo geral (ver docs/estrutura-site.md §4.2 e docs/dominio.md, "Não há papel
 * financeiro").
 */
final class TransparencyDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Direcao->value);
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
