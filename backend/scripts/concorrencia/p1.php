<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Actions\Users\UpdateUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$actor = User::where('email', 'super-a'.CORRIDA_EMAIL_DOMAIN)->firstOrFail();   // A
$target = User::where('email', 'super-b'.CORRIDA_EMAIL_DOMAIN)->firstOrFail();  // B

say('P1', 'abrindo transação externa');

// Transação externa só para segurar o commit; o DB::transaction() de dentro de UpdateUser
// vira SAVEPOINT (comportamento padrão do Laravel quando já há transação aberta), então a
// operação inteira roda exatamente como em produção e as travas ficam retidas até o COMMIT
// daqui. Nenhum gancho foi acrescentado ao código de produção — isto chama a Action de
// verdade, do mesmo jeito que o Controller chamaria.
DB::beginTransaction();

say('P1', 'removendo super_admin de B (caminho real: UpdateUser::handle)');

app(UpdateUser::class)->handle(
    actingUser: $actor,
    target: $target,
    name: $target->name,
    email: $target->email,
    roles: [],
);

say('P1', 'operação feita, ainda SEM commit — travas retidas');
signal('p1-locked');

say('P1', 'esperando P2 encostar na trava...');
waitForSignal('p2-attempting', 30.0);

$deadline = microtime(true) + 15.0;
$blocked = false;
while (microtime(true) < $deadline) {
    if (backendsWaitingOnLock() > 0) {
        $blocked = true;
        break;
    }
    usleep(100_000);
}

say('P1', $blocked ? 'P2 está bloqueado numa trava de linha (pg_stat_activity)' : 'P2 NÃO apareceu bloqueado (atenção)');

say('P1', 'commitando');
DB::commit();
signal('p1-committed');

say('P1', 'commit feito. super_admins ativos agora: '.activeSuperAdminCount());
