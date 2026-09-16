<?php

declare(strict_types=1);

use App\Enums\Role;

test('não autenticado recebe 401', function (): void {
    $this->getJson('/api/v1/roles')->assertUnauthorized();
});

test('direcao não acessa a lista de papéis — mesma ability de listar usuários', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->getJson('/api/v1/roles')->assertForbidden();
});

test('super_admin lista todos os papéis com valor, nome e descrição', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);

    $response = $this->actingAs($admin)->getJson('/api/v1/roles');

    $response->assertOk();
    $values = collect($response->json('data'))->pluck('value')->sort()->values()->all();
    expect($values)->toBe(collect(Role::cases())->map(fn (Role $role) => $role->value)->sort()->values()->all());

    $financeiro = collect($response->json('data'))->firstWhere('value', 'financeiro');
    expect($financeiro['label'])->toBe('Financeiro')
        ->and($financeiro['description'])->toBeString()
        ->and($financeiro['description'])->not->toBe('');
});
