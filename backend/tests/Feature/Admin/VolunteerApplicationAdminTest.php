<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\VolunteerApplication;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar', function (): void {
    $this->getJson('/api/v1/volunteer-applications')->assertUnauthorized();
});

test('comunicacao recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $this->actingAs($user)->getJson('/api/v1/volunteer-applications')->assertForbidden();
});

test('bazar recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Bazar->value);

    $this->actingAs($user)->getJson('/api/v1/volunteer-applications')->assertForbidden();
});

test('direcao lista com dado mascarado', function (): void {
    $user = userWithRole(Role::Direcao->value);
    VolunteerApplication::factory()->create([
        'name' => 'Paula Lima',
        'phone' => '43966660000',
        'email' => 'paula@example.com',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/volunteer-applications');

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('Paula')
        ->and($response->json('data.0.phone'))->toBe('••••0000')
        ->and($response->json('data.0.email'))->toBe('•••@example.com');
});

test('detalhe devolve dado completo e audita o acesso', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $application = VolunteerApplication::factory()->create(['name' => 'Paula Lima']);

    $response = $this->actingAs($user)->getJson("/api/v1/volunteer-applications/{$application->uuid}");

    $response->assertOk()->assertJsonPath('data.name', 'Paula Lima');

    expect(Activity::where('log_name', 'forms')->where('event', 'viewed')->where('subject_id', $application->id)->where('subject_type', VolunteerApplication::class)->exists())->toBeTrue();
});

test('atualizar status preenche handled_by e handled_at automaticamente', function (): void {
    $user = userWithRole(Role::Atendimento->value);
    $application = VolunteerApplication::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/volunteer-applications/{$application->uuid}/status", [
        'status' => 'in_progress',
    ])->assertOk();

    $fresh = $application->fresh();
    expect($fresh->handled_by)->toBe($user->id)
        ->and($fresh->handled_at)->not->toBeNull();
});
