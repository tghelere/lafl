<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

test('troca de senha exige a senha atual correta', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $response = $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-errada',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('current_password');

    expect(Hash::check('senha-atual', $user->fresh()->password))->toBeTrue();
});

test('troca de senha com dados válidos atualiza a senha do usuário', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $response = $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ]);

    $response->assertNoContent();

    expect(Hash::check('nova-senha-forte-123', $user->fresh()->password))->toBeTrue();
});

test('troca de senha exige confirmação igual à nova senha', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'outra-coisa',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});

test('troca de senha sem sessão autenticada retorna 401', function (): void {
    $this->putJson('/api/v1/auth/password', [
        'current_password' => 'x',
        'password' => 'y',
        'password_confirmation' => 'y',
    ])->assertUnauthorized();
});

test('troca de senha registra evento de auditoria', function (): void {
    $user = User::factory()->create(['password' => 'senha-atual']);

    $this->actingAs($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'senha-atual',
        'password' => 'nova-senha-forte-123',
        'password_confirmation' => 'nova-senha-forte-123',
    ])->assertNoContent();

    expect(Activity::where('event', 'password_changed')->where('causer_id', $user->id)->exists())->toBeTrue();
});
