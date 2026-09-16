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
 * testes de Policy por papel (ver docs/dominio.md, seção "Papéis"). Não chama actingAs: cada
 * teste decide explicitamente quando autenticar. Um usuário pode acumular mais de um papel
 * chamando `$user->assignRole(...)` de novo no teste (ver
 * tests/Feature/Authorization/RoleMatrixTest.php).
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
