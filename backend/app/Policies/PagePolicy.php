<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;

/**
 * super_admin não aparece aqui — bypass via Gate::before em AppServiceProvider (ver
 * docs/dominio.md, seção "Papéis"). `direcao` e `comunicacao` leem e editam página existente
 * igual — mas só `direcao` cria e exclui página. `comunicacao` é o papel de quem escreve o
 * conteúdo do dia a dia (título, texto, SEO); criar uma página nova ou apagar uma existente
 * mexe na estrutura do site (o que o Nuxt prerenderiza, o que sai do ar) — mesmo peso de
 * decisão que `managePublication` já reserva a `direcao`. `atendimento` não administra
 * conteúdo (formulário recebido é a área dela, não página).
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
        return $user->hasRole(Role::Direcao->value);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasRole(Role::Direcao->value);
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
