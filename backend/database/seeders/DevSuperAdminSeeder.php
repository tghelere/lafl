<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevSuperAdminSeeder extends Seeder
{
    /**
     * Cria um usuário super_admin sintético para desenvolvimento local. Nunca roda fora de
     * local/testing — não é um mecanismo de bootstrap de produção (ver CLAUDE.md, regra 10:
     * nenhum dado real em seeder).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'dev@laranaliafranco.local'],
            [
                'name' => 'Dev Super Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        // Reativa de propósito. `migrate:fresh --seed` recria a tabela e nunca cai neste
        // caso, mas `db:seed` sozinho, num banco que já existe, cairia: desativar o próprio
        // dev pelo CRUD de usuários do painel (App\Actions\Users\DeactivateUser) deixa
        // `deactivated_at` preenchido, e reseedar não o limparia — `updateOrCreate` só
        // escreve as colunas que recebe. O resultado seria um login recusado com
        // "Esta conta foi desativada" logo depois de rodar o seeder que existe justamente
        // para devolver o acesso. Fora de local/testing isto nem chega aqui (guarda acima).
        $user->forceFill(['deactivated_at' => null])->save();

        $user->assignRole(Role::SuperAdmin->value);
    }
}
