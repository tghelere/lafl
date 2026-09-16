<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base compartilhada pelas seis Policies de formulário recebido (ver docs/dominio.md, seção
 * "Papéis") — `direcao` acessa todos, cada subclasse soma o papel da área dona daquele
 * formulário por cima do que a base já dá (`comunicacao` nunca aparece em nenhuma: não tem
 * acesso a formulário recebido nem a dado de pessoa).
 *
 * super_admin não aparece aqui — bypass via Gate::before em AppServiceProvider.
 *
 * Nenhuma tela de admin usa isto ainda (ver docs/roadmap.md, "a leitura vem depois") — a
 * Policy existe e é testada agora porque a regra de acesso é parte do desenho da entidade,
 * não da tela que a consome.
 */
abstract class FormSubmissionPolicy
{
    /**
     * @return list<string>
     */
    protected function allowedRoles(): array
    {
        return [Role::Direcao->value];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Model $submission): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $submission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $submission): bool
    {
        return $this->viewAny($user);
    }
}
