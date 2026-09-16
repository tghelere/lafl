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

        // ->count() depois de lockForUpdate() vira `SELECT count(*) ... FOR UPDATE` — o
        // PostgreSQL recusa travar linha em cima de função de agregação (SQLite deixava
        // passar, porque lockForUpdate() é um no-op lá; nunca trava nada de verdade). Selecionar
        // as linhas e contar com count() nativo do PHP (Collection implementa Countable) trava
        // cada linha que qualifica, sem agregação na mesma consulta — ->count() do Collection
        // faria sentido pedir de volta ao banco, mas aqui as linhas já estão na mão.
        $activeSuperAdmins = User::query()
            ->role(Role::SuperAdmin->value)
            ->active()
            ->lockForUpdate()
            ->get(['id']);

        if (count($activeSuperAdmins) <= 1) {
            throw ValidationException::withMessages([
                'roles' => ['Não é possível remover ou desativar o último super administrador ativo.'],
            ]);
        }
    }
}
