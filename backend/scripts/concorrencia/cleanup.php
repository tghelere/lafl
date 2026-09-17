<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Desfaz tudo que setup.php fez: reativa quem foi desativado temporariamente e apaga os dois
 * usuários de teste (papel e registro de auditoria incluídos). Idempotente — rodar de novo
 * sem nada pendente não faz nada, e não é erro. Chamado sempre pelo run.sh (mesmo se a
 * corrida falhar no meio, via `trap` do bash), mas pode ser rodado sozinho para restaurar
 * manualmente depois de uma interrupção anormal (kill -9, por exemplo).
 */
$stateFile = SIGNAL_DIR.'/desativados-temporariamente.json';

if (file_exists($stateFile)) {
    $uuids = json_decode(file_get_contents($stateFile), true, flags: JSON_THROW_ON_ERROR);

    foreach ($uuids as $uuid) {
        $user = User::where('uuid', $uuid)->first();

        if ($user === null) {
            say('cleanup', "aviso: usuário {$uuid} não existe mais, pulando reativação");

            continue;
        }

        $user->forceFill(['deactivated_at' => null])->save();
        say('cleanup', "reativado: {$user->email}");
    }

    unlink($stateFile);
} else {
    say('cleanup', 'nenhum estado de desativação temporária pendente');
}

$corridaUsers = User::where('email', 'like', '%'.CORRIDA_EMAIL_DOMAIN)->get();

foreach ($corridaUsers as $user) {
    Activity::where('causer_type', User::class)->where('causer_id', $user->id)->delete();
    Activity::where('subject_type', User::class)->where('subject_id', $user->id)->delete();
    $user->syncRoles([]);
    $user->delete();
    say('cleanup', "removido: {$user->email}");
}

if ($corridaUsers->isEmpty()) {
    say('cleanup', 'nenhum usuário de corrida (@corrida.local) para remover');
}

clearSignals();

say('cleanup', 'super_admins ativos no banco agora: '.activeSuperAdminCount());
