<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * migrate:fresh antes de tudo: o banco de teste dedicado começa vazio a cada execução, então
 * não há super_admin nenhum além dos dois que este script cria — sem isso, qualquer usuário
 * deixado por uma execução anterior, ou pela própria suíte Pest, poderia mascarar a
 * demonstração (um terceiro super_admin ativo sobraria de pé, e a regra só barra reduzir a
 * ZERO, não a um). bootstrap.php já garantiu que a conexão resolvida é o banco de teste, não o
 * de desenvolvimento — apagar tudo aqui é seguro.
 */
Artisan::call('migrate:fresh', ['--force' => true]);

// migrate:fresh só recria as tabelas — o papel super_admin precisa existir na tabela roles
// antes de assignRole() poder atribuí-lo.
(new RoleSeeder)->run();

foreach (['a', 'b'] as $letter) {
    $email = "super-{$letter}".CORRIDA_EMAIL_DOMAIN;

    $user = User::factory()->create([
        'name' => 'Super '.strtoupper($letter).' (corrida)',
        'email' => $email,
        'deactivated_at' => null,
    ]);
    $user->assignRole(Role::SuperAdmin->value);
}

clearSignals();

say('setup', 'banco: '.DB::connection()->getDatabaseName());
say('setup', 'super-a e super-b criados, ambos super_admin ativos');
say('setup', 'super_admins ativos agora (deve ser exatamente 2): '.activeSuperAdminCount());
