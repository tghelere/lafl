<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Cria os papéis descritos em docs/dominio.md, seção "Papéis e permissões".
     */
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
