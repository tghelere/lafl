<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;

/**
 * super_admin não aparece aqui — bypass via Gate::before em AppServiceProvider (ver
 * docs/estrutura-site.md §4.4: "super_admin" é sempre "total").
 */
final class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::Direcao->value, Role::Atendimento->value, Role::Comunicacao->value]);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::Direcao->value, Role::Comunicacao->value]);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->create($user);
    }
}
