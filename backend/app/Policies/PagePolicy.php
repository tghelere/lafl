<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;

/**
 * super_admin não aparece aqui — bypass via Gate::before em AppServiceProvider (ver
 * docs/dominio.md, seção "Papéis"). `direcao` e `comunicacao` têm leitura e escrita iguais —
 * `atendimento` não administra conteúdo (formulário recebido é a área dela, não página).
 */
final class PagePolicy
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

    public function view(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Endereço público (slug) e situação de publicação da página. Separado de `update`
     * porque mexer no texto e mexer na estrutura do site têm peso diferente: trocar o slug
     * quebra link já divulgado e muda o que o Nuxt prerenderiza; publicar/despublicar tira
     * uma página do ar. `comunicacao` escreve o conteúdo do dia a dia mas não decide isso —
     * só `direcao` (e `super_admin`, pelo Gate::before em AppServiceProvider).
     */
    public function managePublication(User $user, Page $page): bool
    {
        return $user->hasRole(Role::Direcao->value);
    }
}
