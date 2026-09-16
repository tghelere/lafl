<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Desativar não derruba uma sessão já aberta sozinho — o cookie continua válido até a
 * próxima requisição passar por App\Http\Middleware\EnsureUserIsActive (ver
 * routes/api_v1.php, grupo $authenticated).
 */
test('usuário desativado com sessão aberta perde o acesso na requisição seguinte', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/auth/user')->assertOk();

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user)->getJson('/api/v1/auth/user')->assertUnauthorized();
});

test('usuário reativado volta a acessar normalmente', function (): void {
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->actingAs($user)->getJson('/api/v1/auth/user')->assertUnauthorized();

    $user->forceFill(['deactivated_at' => null])->save();

    $this->actingAs($user)->getJson('/api/v1/auth/user')->assertOk();
});
