<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->user = User::factory()->create(['password' => 'senha-correta']);
});

test('login com credenciais corretas autentica e retorna o usuário', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'senha-correta',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->user->uuid)
        ->assertJsonPath('data.email', $this->user->email)
        ->assertJsonMissing(['password']);

    $this->assertAuthenticatedAs($this->user);
});

test('login com senha errada retorna 422 e não autentica', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'senha-errada',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');

    $this->assertGuest();
});

test('login registra evento de auditoria', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'senha-correta',
    ])->assertOk();

    expect(Activity::where('event', 'login')->where('causer_id', $this->user->id)->exists())->toBeTrue();
});

test('login de usuário desativado com senha correta recusa com mensagem específica', function (): void {
    $this->user->forceFill(['deactivated_at' => now()])->save();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'senha-correta',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email')
        ->assertJsonFragment(['email' => ['Esta conta foi desativada. Fale com a direção.']]);

    $this->assertGuest('web');
});

test('login de usuário desativado com senha errada recusa com a mensagem genérica, não a de desativado', function (): void {
    $this->user->forceFill(['deactivated_at' => now()])->save();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'senha-errada',
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['email' => ['Credenciais inválidas.']]);

    $this->assertGuest('web');
});

test('login é limitado por rate limit', function (): void {
    RateLimiter::clear('login');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'senha-errada',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $this->user->email,
        'password' => 'senha-errada',
    ])->assertStatus(429);
});
