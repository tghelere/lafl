<?php

declare(strict_types=1);

use App\Models\User;

test('retorna 401 sem sessão autenticada', function (): void {
    $this->getJson('/api/v1/auth/user')->assertUnauthorized();
});

test('retorna o usuário autenticado, expondo uuid e nunca o id sequencial', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->uuid)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.two_factor_secret');
});
