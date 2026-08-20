<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Cria (e semeia os papéis, se preciso) um usuário sintético com o papel dado — usado nos
 * testes de Policy por papel (ver docs/estrutura-site.md §4.4). Não chama actingAs: cada
 * teste decide explicitamente quando autenticar.
 */
function userWithRole(string $role): User
{
    if (! Role::where('name', $role)->exists()) {
        test()->seed(RoleSeeder::class);
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}
