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

        $user->assignRole(Role::SuperAdmin->value);
    }
}
