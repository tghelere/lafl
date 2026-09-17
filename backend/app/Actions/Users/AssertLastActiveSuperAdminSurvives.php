<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chamar dentro de `DB::transaction()`, antes de desativar um usuário ou de sincronizar
 * papéis que removeriam `super_admin` dele — nunca depois.
 *
 * A serialização é feita travando **uma linha só**: a do papel `super_admin` em `roles`. Toda
 * operação que pode reduzir o total de super_admins ativos passa por esse mutex antes de
 * contar, então elas viram fila em vez de rodarem em paralelo.
 *
 * A versão anterior travava as linhas de `users` dos super_admins ativos e contava a coleção
 * travada — e não bastava. Remover o papel não altera a linha de `users`, só a pivot
 * `model_has_roles`: em READ COMMITTED (padrão do PostgreSQL), a segunda transação ficava
 * mesmo bloqueada na trava, mas ao ser liberada terminava o `SELECT ... FOR UPDATE` com o
 * snapshot do início daquele comando — snapshot que ainda enxergava o vínculo de papel que a
 * primeira transação acabara de apagar. As duas contavam 2, as duas passavam, e o sistema
 * ficava com zero super_admin ativo. Reproduzido com dois processos concorrentes de verdade
 * contra o Postgres local antes da correção (ver docs/relatorio-sessao-9.md).
 *
 * Por que agora funciona: a contagem é um comando NOVO, disparado depois de a trava do mutex
 * ter sido concedida. Em READ COMMITTED cada comando tira um snapshot novo, então esse
 * segundo comando enxerga tudo o que a transação anterior commitou — inclusive a remoção de
 * papel que o snapshot antigo escondia. Daí a contagem poder ser um `count()` comum, sem
 * `FOR UPDATE`: quem garante a exclusão mútua é o mutex, não a trava das linhas contadas.
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

        $this->acquireSuperAdminMutex();

        $activeSuperAdminCount = User::query()
            ->role(Role::SuperAdmin->value)
            ->active()
            ->count();

        if ($activeSuperAdminCount <= 1) {
            throw ValidationException::withMessages([
                'roles' => ['Não é possível remover ou desativar o último super administrador ativo.'],
            ]);
        }
    }

    /**
     * Trava a linha do papel `super_admin` até o fim da transação de quem chamou. A linha em
     * si não é lida para nada — serve só como ponto único de encontro. Consulta crua em vez do
     * model do spatie porque o que importa aqui é o `FOR UPDATE` na linha, não o objeto.
     */
    private function acquireSuperAdminMutex(): void
    {
        DB::table(config('permission.table_names.roles', 'roles'))
            ->where('name', Role::SuperAdmin->value)
            ->lockForUpdate()
            ->first();
    }
}
