<?php

declare(strict_types=1);

use App\Actions\Users\AssertLastActiveSuperAdminSurvives;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar usuários', function (): void {
    $this->getJson('/api/v1/users')->assertUnauthorized();
});

test('direcao não administra usuários — só super_admin', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $target = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/users')->assertForbidden();
    $this->actingAs($user)->getJson("/api/v1/users/{$target->uuid}")->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x@example.com',
        'roles' => [Role::Atendimento->value],
    ])->assertForbidden();
    $this->actingAs($user)->patchJson("/api/v1/users/{$target->uuid}/deactivate")->assertForbidden();
    $this->actingAs($user)->patchJson("/api/v1/users/{$target->uuid}/reactivate")->assertForbidden();
});

test('super_admin cria usuário sem senha utilizável e com os papéis informados', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);

    $response = $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'Nova Pessoa',
        'email' => 'nova@example.com',
        'roles' => [Role::Financeiro->value, Role::Contraturno->value],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Nova Pessoa')
        ->assertJsonPath('data.email', 'nova@example.com')
        ->assertJsonPath('data.active', true);

    $created = User::where('email', 'nova@example.com')->firstOrFail();
    expect($created->getRoleNames()->sort()->values()->all())->toBe(['contraturno', 'financeiro']);

    // Nasce sem senha utilizável — nenhuma senha adivinhável autentica.
    expect(Hash::check('password', $created->password))->toBeFalse()
        ->and(Hash::check('', $created->password))->toBeFalse();
});

test('criar usuário exige ao menos um papel válido do enum', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x@example.com',
        'roles' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('roles');

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x2@example.com',
        'roles' => ['papel-que-nao-existe'],
    ])->assertStatus(422)->assertJsonValidationErrors('roles.0');
});

test('UserAccountResource nunca expõe senha, remember_token ou 2FA', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->getJson("/api/v1/users/{$target->uuid}");

    $response->assertOk()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.two_factor_enabled')
        ->assertJsonMissingPath('data.two_factor_secret');
});

test('listagem busca por nome ou e-mail e filtra por ativo/inativo', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    User::factory()->create(['name' => 'Beatriz Nogueira', 'email' => 'beatriz@example.com']);
    User::factory()->create(['name' => 'Carlos Dias', 'email' => 'carlos@example.com', 'deactivated_at' => now()]);

    $bySearch = $this->actingAs($admin)->getJson('/api/v1/users?search=beatriz');
    $bySearch->assertOk();
    expect(collect($bySearch->json('data'))->pluck('email'))->toContain('beatriz@example.com');

    $onlyInactive = $this->actingAs($admin)->getJson('/api/v1/users?status=inactive');
    $onlyInactive->assertOk();
    expect(collect($onlyInactive->json('data'))->pluck('email')->all())->toBe(['carlos@example.com']);

    $onlyActive = $this->actingAs($admin)->getJson('/api/v1/users?status=active');
    expect(collect($onlyActive->json('data'))->pluck('email'))->not->toContain('carlos@example.com');
});

test('atualizar usuário troca nome, e-mail e papéis, e audita a mudança de papel', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create(['name' => 'Antigo']);
    $target->assignRole(Role::Bazar->value);

    $response = $this->actingAs($admin)->putJson("/api/v1/users/{$target->uuid}", [
        'name' => 'Novo Nome',
        'email' => $target->email,
        'roles' => [Role::Atendimento->value],
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Novo Nome');
    expect($target->fresh()->getRoleNames()->all())->toBe(['atendimento']);

    $activity = Activity::where('log_name', 'users')->where('event', 'roles_updated')->where('subject_id', $target->id)->first();
    expect($activity)->not->toBeNull()
        ->and($activity->properties['from'])->toBe(['bazar'])
        ->and($activity->properties['to'])->toBe(['atendimento']);
});

test('ninguém desativa a própria conta', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    userWithRole(Role::SuperAdmin->value); // segundo super_admin, para não confundir com a proteção de "último".

    $response = $this->actingAs($admin)->patchJson("/api/v1/users/{$admin->uuid}/deactivate");

    $response->assertStatus(422)->assertJsonValidationErrors('user');
    expect($admin->fresh()->deactivated_at)->toBeNull();
});

test('ninguém remove o próprio papel de super_admin', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    userWithRole(Role::SuperAdmin->value); // segundo super_admin ativo, para isolar da proteção de "último".

    $response = $this->actingAs($admin)->putJson("/api/v1/users/{$admin->uuid}", [
        'name' => $admin->name,
        'email' => $admin->email,
        'roles' => [Role::Direcao->value],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('roles');
    expect($admin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

/**
 * Não dá para exercitar "outro super_admin desativa o último" pela API: quem chama o
 * endpoint precisa ser super_admin ativo (Gate::before), então se o alvo é o ÚNICO
 * super_admin ativo, o próprio ator só pode ser ele mesmo — cai na regra "ninguém desativa a
 * si mesmo" antes de chegar aqui. A proteção contra reduzir a zero super_admins ativos só é
 * alcançável de verdade por duas requisições concorrentes (dois super_admins se
 * desativando/despapelando um ao outro ao mesmo tempo) — o que o lock com transação existe
 * para impedir, mas que o client de teste HTTP síncrono do Pest não reproduz (uma única
 * conexão de banco, sem paralelismo real). Testada aqui direto na Action, isolada da regra
 * de autoproteção.
 */
test('AssertLastActiveSuperAdminSurvives bloqueia só quando o alvo é o único super_admin ativo', function (): void {
    $action = app(AssertLastActiveSuperAdminSurvives::class);

    $onlySuperAdmin = userWithRole(Role::SuperAdmin->value);

    expect(fn () => $action->handle($onlySuperAdmin))
        ->toThrow(ValidationException::class);

    $secondSuperAdmin = userWithRole(Role::SuperAdmin->value);

    // Com dois ativos, remover/desativar um deles não é o "último" — não bloqueia.
    $action->handle($onlySuperAdmin);
    $action->handle($secondSuperAdmin);

    // Alvo já inativo não ameaça o total de ativos, mesmo mantendo o papel.
    $inactiveSuperAdmin = User::factory()->create(['deactivated_at' => now()]);
    $inactiveSuperAdmin->assignRole(Role::SuperAdmin->value);
    $action->handle($inactiveSuperAdmin);

    // Papel diferente de super_admin nunca é protegido por esta regra.
    $regularUser = userWithRole(Role::Direcao->value);
    $action->handle($regularUser);
});

test('desativar um super_admin que não é o último funciona normalmente', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $other = userWithRole(Role::SuperAdmin->value);

    $this->actingAs($admin)->patchJson("/api/v1/users/{$other->uuid}/deactivate")->assertOk();

    expect($other->fresh()->deactivated_at)->not->toBeNull();
});

test('reativar devolve o acesso', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create(['deactivated_at' => now()]);

    $response = $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}/reactivate");

    $response->assertOk()->assertJsonPath('data.active', true);
    expect($target->fresh()->deactivated_at)->toBeNull();
});

test('desativação e reativação são auditadas automaticamente', function (): void {
    $admin = userWithRole(Role::SuperAdmin->value);
    $target = User::factory()->create();

    $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}/deactivate")->assertOk();
    $this->actingAs($admin)->patchJson("/api/v1/users/{$target->uuid}/reactivate")->assertOk();

    expect(Activity::where('log_name', 'users')->where('subject_id', $target->id)->where('event', 'updated')->count())->toBe(2);
});
