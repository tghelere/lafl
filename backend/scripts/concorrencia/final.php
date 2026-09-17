<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Models\User;

$corridaUsers = User::where('email', 'like', '%'.CORRIDA_EMAIL_DOMAIN)->orderBy('email')->get();
$total = activeSuperAdminCount();

say('final', 'super_admins ATIVOS no sistema inteiro: '.$total);

foreach ($corridaUsers as $user) {
    say('final', sprintf(
        '  %-28s papéis=[%s] ativo=%s',
        $user->email,
        $user->getRoleNames()->implode(','),
        $user->deactivated_at === null ? 'sim' : 'não',
    ));
}

say('final', $total === 0
    ? '>>> INVARIANTE VIOLADO: o sistema ficou sem nenhum super_admin ativo.'
    : '>>> Invariante preservado: ainda existe super_admin ativo.');
