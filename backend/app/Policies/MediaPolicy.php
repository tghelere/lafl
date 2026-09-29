<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Media;
use App\Models\User;

/**
 * Mesma matriz de App\Policies\PagePolicy, porque a biblioteca existe para o conteúdo das
 * páginas: `direcao` e `comunicacao` veem, enviam, substituem e editam; só `direcao` exclui —
 * o mesmo peso que excluir uma página tem lá. `super_admin` passa pelo Gate::before em
 * AppServiceProvider.
 *
 * Substituir o arquivo é `update`: troca o que aparece no site, como editar o texto de uma
 * página troca, e `comunicacao` faz isso no dia a dia.
 */
final class MediaPolicy
{
    /**
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        return [Role::Direcao->value, Role::Comunicacao->value];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->hasRole(Role::Direcao->value);
    }
}
