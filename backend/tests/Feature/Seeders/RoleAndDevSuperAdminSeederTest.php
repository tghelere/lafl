<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\DevSuperAdminSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

test('RoleSeeder cria todos os papéis de docs/dominio.md', function (): void {
    $this->seed(RoleSeeder::class);

    $names = Role::pluck('name')->all();

    foreach (RoleEnum::cases() as $role) {
        expect($names)->toContain($role->value);
    }

    expect(Role::count())->toBe(count(RoleEnum::cases()));
});

test('DevSuperAdminSeeder cria um usuário sintético com o papel super_admin', function (): void {
    $this->seed([RoleSeeder::class, DevSuperAdminSeeder::class]);

    $user = User::where('email', 'dev@laranaliafranco.local')->first();

    expect($user)->not->toBeNull()
        ->and($user->uuid)->not->toBeNull()
        ->and($user->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
});

test('DevSuperAdminSeeder não roda fora de local/testing', function (): void {
    app(RoleSeeder::class)->run();

    app()['env'] = 'production';
    // Instanciado direto (não via `db:seed`) para não disparar a confirmação de produção
    // do próprio Artisan — o que testamos aqui é o guard de ambiente dentro do seeder.
    app(DevSuperAdminSeeder::class)->run();
    app()['env'] = 'testing';

    expect(User::where('email', 'dev@laranaliafranco.local')->exists())->toBeFalse();
});
