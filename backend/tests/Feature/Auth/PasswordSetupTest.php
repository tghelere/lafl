<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

function extractSetupToken(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return (string) $query['token'];
}

test('só super_admin gera link de definição de senha', function (): void {
    $direcao = userWithRole(Role::Direcao->value);
    $target = User::factory()->create();

    $this->actingAs($direcao)->postJson("/api/v1/users/{$target->uuid}/password-link")->assertForbidden();
});

test('gerar link devolve a url uma vez, sem guardar nem logar o token', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link");

    $response->assertOk();
    $url = $response->json('data.url');
    expect($url)->toStartWith(config('forms.admin_base_url').'/definir-senha?token=');

    $activity = Activity::where('log_name', 'users')->where('event', 'password_link_generated')->where('subject_id', $target->id)->first();
    expect($activity)->not->toBeNull();

    $token = extractSetupToken($url);
    $serialized = json_encode($activity->properties).($activity->description ?? '');
    expect($serialized)->not->toContain($token)
        ->and($serialized)->not->toContain('definir-senha');
});

test('não é possível gerar link para usuário desativado', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create(['deactivated_at' => now()]);

    $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

test('definir senha com token e e-mail válidos permite logar com a nova senha', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $url = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $token = extractSetupToken($url);

    $response = $this->postJson('/api/v1/auth/set-password', [
        'token' => $token,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ]);

    $response->assertNoContent();
    expect(Hash::check('senha-nova-forte-123', $target->fresh()->password))->toBeTrue();

    $this->postJson('/api/v1/auth/login', [
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
    ])->assertOk();
});

test('definir senha com e-mail que não confere devolve erro genérico', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();
    $other = User::factory()->create();

    $url = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $token = extractSetupToken($url);

    $response = $this->postJson('/api/v1/auth/set-password', [
        'token' => $token,
        'email' => $other->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ]);

    $response->assertStatus(422)->assertJsonFragment(['token' => ['Link inválido ou expirado.']]);
});

test('definir senha com token inválido devolve o mesmo erro genérico', function (): void {
    $target = User::factory()->create();

    $this->postJson('/api/v1/auth/set-password', [
        'token' => 'token-que-nao-existe',
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ])->assertStatus(422)->assertJsonFragment(['token' => ['Link inválido ou expirado.']]);
});

test('usar o mesmo link duas vezes falha na segunda', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $url = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $token = extractSetupToken($url);

    $payload = [
        'token' => $token,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ];

    $this->postJson('/api/v1/auth/set-password', $payload)->assertNoContent();

    $secondAttempt = $this->postJson('/api/v1/auth/set-password', [
        ...$payload,
        'password' => 'outra-senha-forte-456',
        'password_confirmation' => 'outra-senha-forte-456',
    ]);

    $secondAttempt->assertStatus(422)->assertJsonFragment(['token' => ['Link inválido ou expirado.']]);
    // A senha definida na primeira tentativa continua valendo — a segunda não sobrescreveu.
    expect(Hash::check('senha-nova-forte-123', $target->fresh()->password))->toBeTrue();
});

test('link expirado (mais de 24h) falha', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $url = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $token = extractSetupToken($url);

    $this->travel(24)->hours();
    $this->travel(1)->minutes();

    $this->postJson('/api/v1/auth/set-password', [
        'token' => $token,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ])->assertStatus(422)->assertJsonFragment(['token' => ['Link inválido ou expirado.']]);

    $this->travelBack();
});

test('gerar um novo link invalida o anterior', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $firstUrl = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $firstToken = extractSetupToken($firstUrl);

    $secondUrl = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $secondToken = extractSetupToken($secondUrl);

    expect($firstToken)->not->toBe($secondToken);

    // O link antigo não funciona mais.
    $this->postJson('/api/v1/auth/set-password', [
        'token' => $firstToken,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ])->assertStatus(422)->assertJsonFragment(['token' => ['Link inválido ou expirado.']]);

    // O link novo funciona normalmente.
    $this->postJson('/api/v1/auth/set-password', [
        'token' => $secondToken,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ])->assertNoContent();
});

test('nenhum log de auditoria do ciclo de senha guarda a senha em texto puro ou hasheada', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $url = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $token = extractSetupToken($url);

    $this->postJson('/api/v1/auth/set-password', [
        'token' => $token,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ])->assertNoContent();

    $hash = $target->fresh()->password;

    $everyLogEntry = Activity::all()->map(fn (Activity $activity): string => json_encode([
        $activity->description,
        $activity->properties,
    ]))->implode(' ');

    expect($everyLogEntry)->not->toContain('senha-nova-forte-123')
        ->and($everyLogEntry)->not->toContain($hash)
        ->and($everyLogEntry)->not->toContain($token);
});

test('usuário desativado depois de gerar o link não consegue mais usá-lo', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $url = $this->actingAs($admin)->postJson("/api/v1/users/{$target->uuid}/password-link")->json('data.url');
    $token = extractSetupToken($url);

    $target->forceFill(['deactivated_at' => now()])->save();

    $this->postJson('/api/v1/auth/set-password', [
        'token' => $token,
        'email' => $target->email,
        'password' => 'senha-nova-forte-123',
        'password_confirmation' => 'senha-nova-forte-123',
    ])->assertStatus(422)->assertJsonFragment(['token' => ['Link inválido ou expirado.']]);
});
