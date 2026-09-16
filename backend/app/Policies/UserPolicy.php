<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Gestão de usuários não abre para nenhum papel de área — nem `direcao`, que administra todo
 * o resto (ver docs/dominio.md, seção "Papéis"), cria ou desativa conta de colega. Toda
 * ability aqui é `false`: quem passa é só `super_admin`, via `Gate::before` em
 * AppServiceProvider::configureAuthorization() — os métodos abaixo nunca chegam a rodar para
 * esse papel, o bypass decide antes.
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        return false;
    }

    public function deactivate(User $user, User $target): bool
    {
        return false;
    }

    public function reactivate(User $user, User $target): bool
    {
        return false;
    }

    public function generatePasswordLink(User $user, User $target): bool
    {
        return false;
    }
}
