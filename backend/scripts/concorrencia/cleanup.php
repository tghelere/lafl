<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Apaga os dois usuários de teste (papel e registro de auditoria incluídos). Idempotente —
 * rodar de novo sem nenhum usuário de corrida não faz nada, e não é erro. Chamado sempre pelo
 * run.sh (mesmo se a corrida falhar no meio, via `trap` do bash), mas pode ser rodado sozinho
 * a qualquer momento — como o banco de teste é recriado do zero (migrate:fresh) a cada
 * execução de setup.php, não há muito a limpar aqui além de deixar o estado explícito.
 */
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

say('cleanup', 'super_admins ativos no banco de teste agora: '.activeSuperAdminCount());
