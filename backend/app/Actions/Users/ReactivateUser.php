<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Sem proteção de "última conta ativa" — reativar só devolve acesso, nunca tira; não há
 * cenário em que reativar alguém derruba o total de super_admin ativos a zero.
 */
final class ReactivateUser
{
    public function handle(User $target): User
    {
        $target->forceFill(['deactivated_at' => null])->save();

        return $target;
    }
}
