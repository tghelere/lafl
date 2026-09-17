<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A proteção conta super_admins ATIVOS no sistema inteiro, não só os dois usuários de teste.
 * Rodando contra um banco de desenvolvimento de verdade — que já tem pelo menos o
 * dev@laranaliafranco.local — sobrar um terceiro super_admin ativo mascararia a demonstração:
 * as duas remoções concorrentes seriam permitidas sem problema nenhum, porque o terceiro
 * continuaria de pé (a regra só barra reduzir a ZERO, não a um).
 *
 * Por isso este setup DESATIVA TEMPORARIAMENTE qualquer super_admin ativo que não seja um dos
 * dois usuários de teste, e grava quem foi desativado em .signals/desativados-temporariamente.
 * json para cleanup.php reverter no final — mesmo se o script quebrar no meio, "php
 * cleanup.php" sozinho restaura pelo arquivo. Nada além de deactivated_at é tocado (papel,
 * senha, e-mail continuam intactos).
 */
clearSignals();

$stateFile = SIGNAL_DIR.'/desativados-temporariamente.json';

if (file_exists($stateFile)) {
    fwrite(STDERR, "Já existe um estado de desativação temporária pendente ({$stateFile}).\n");
    fwrite(STDERR, "Rode primeiro: php cleanup.php\n");
    exit(1);
}

$otherActiveSuperAdmins = User::query()
    ->role(Role::SuperAdmin->value)
    ->active()
    ->where('email', 'not like', '%'.CORRIDA_EMAIL_DOMAIN)
    ->get(['id', 'uuid', 'email']);

@mkdir(SIGNAL_DIR, 0777, true);
file_put_contents($stateFile, json_encode(
    $otherActiveSuperAdmins->pluck('uuid')->all(),
    JSON_PRETTY_PRINT,
));

foreach ($otherActiveSuperAdmins as $admin) {
    $admin->forceFill(['deactivated_at' => now()])->save();
    say('setup', "desativado temporariamente: {$admin->email} (restaurado por cleanup.php)");
}

foreach (['a', 'b'] as $letter) {
    $email = "super-{$letter}".CORRIDA_EMAIL_DOMAIN;

    User::where('email', $email)->delete();

    $user = User::factory()->create([
        'name' => 'Super '.strtoupper($letter).' (corrida)',
        'email' => $email,
        'deactivated_at' => null,
    ]);
    $user->assignRole(Role::SuperAdmin->value);
}

say('setup', 'banco: '.DB::connection()->getDatabaseName());
say('setup', 'super-a e super-b criados, ambos super_admin ativos');
say('setup', 'super_admins ativos agora (deve ser exatamente 2): '.activeSuperAdminCount());
