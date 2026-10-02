<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Partner;
use App\Models\User;

/**
 * Quem edita as páginas do site cuida dos parceiros: `direcao` e `comunicacao`, com as mesmas
 * permissões de ver, criar, editar e excluir (a página de parceiros é conteúdo do dia a dia, e
 * não estrutura do site). super_admin passa pelo Gate::before em AppServiceProvider.
 */
final class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::Direcao->value, Role::Comunicacao->value]);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $this->viewAny($user);
    }
}
