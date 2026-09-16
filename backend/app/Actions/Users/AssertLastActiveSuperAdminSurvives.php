<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Chamar dentro de `DB::transaction()`, antes de desativar um usuário ou de sincronizar
 * papéis que removeriam `super_admin` dele — nunca depois. `lockForUpdate()` trava as linhas
 * dos super_admins ativos até a transação chamadora terminar (commit ou rollback), então duas
 * requisições concorrentes (ex.: dois super_admins removendo o papel um do outro ao mesmo
 * tempo) não conseguem as duas ler "ainda sobra um" antes de qualquer uma escrever — a
 * segunda só destrava depois que a primeira commitou, e nesse ponto reconta com o estado já
 * atualizado.
 */
final class AssertLastActiveSuperAdminSurvives
{
    public function handle(User $target): void
    {
        // Alvo já inativo não ameaça o total de super_admin ativos, esteja com o papel ou
        // não — só protege quem hoje conta para esse total.
        if (! $target->hasRole(Role::SuperAdmin->value) || $target->deactivated_at !== null) {
            return;
        }

        $activeSuperAdminCount = User::query()
            ->role(Role::SuperAdmin->value)
            ->active()
            ->lockForUpdate()
            ->count();

        if ($activeSuperAdminCount <= 1) {
            throw ValidationException::withMessages([
                'roles' => ['Não é possível remover ou desativar o último super administrador ativo.'],
            ]);
        }
    }
}
