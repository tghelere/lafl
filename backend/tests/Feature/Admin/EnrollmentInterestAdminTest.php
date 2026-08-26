<?php

declare(strict_types=1);

use App\Enums\FormSubmissionStatus;
use App\Enums\Role;
use App\Models\EnrollmentInterest;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar', function (): void {
    $this->getJson('/api/v1/enrollment-interests')->assertUnauthorized();
});

test('comunicacao recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $this->actingAs($user)->getJson('/api/v1/enrollment-interests')->assertForbidden();
});

test('direcao lista com dado mascarado', function (): void {
    $user = userWithRole(Role::Direcao->value);
    EnrollmentInterest::factory()->create([
        'guardian_name' => 'Maria da Silva',
        'phone' => '43999990000',
        'email' => 'maria@example.com',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/enrollment-interests');

    $response->assertOk();
    expect($response->json('data.0.guardian_name'))->toBe('Maria')
        ->and($response->json('data.0.phone'))->toBe('••••0000')
        ->and($response->json('data.0.email'))->toBe('•••@example.com')
        ->and($response->json('data.0.guardian_name'))->not->toContain('Silva');
});

test('listagem filtra por status', function (): void {
    $user = userWithRole(Role::Direcao->value);
    EnrollmentInterest::factory()->create(['status' => FormSubmissionStatus::New]);
    EnrollmentInterest::factory()->create(['status' => FormSubmissionStatus::Done]);

    $response = $this->actingAs($user)->getJson('/api/v1/enrollment-interests?status=done');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.status'))->toBe('done');
});

test('listagem filtra por período', function (): void {
    $user = userWithRole(Role::Direcao->value);
    EnrollmentInterest::factory()->create(['created_at' => now()->subDays(10)]);
    EnrollmentInterest::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($user)->getJson('/api/v1/enrollment-interests?from='.now()->subDay()->toDateString());

    expect($response->json('data'))->toHaveCount(1);
});

test('detalhe devolve dado completo e audita o acesso', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $interest = EnrollmentInterest::factory()->create([
        'guardian_name' => 'Maria da Silva',
        'email' => 'maria@example.com',
    ]);

    $response = $this->actingAs($user)->getJson("/api/v1/enrollment-interests/{$interest->uuid}");

    $response->assertOk()
        ->assertJsonPath('data.guardian_name', 'Maria da Silva')
        ->assertJsonPath('data.email', 'maria@example.com');

    $activity = Activity::where('log_name', 'forms')
        ->where('event', 'viewed')
        ->where('subject_id', $interest->id)
        ->where('subject_type', EnrollmentInterest::class)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and(json_encode($activity->properties))->not->toContain('Maria da Silva')
        ->and(json_encode($activity->properties))->not->toContain('maria@example.com');
});

test('comunicacao recebe 403 no detalhe', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $interest = EnrollmentInterest::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/enrollment-interests/{$interest->uuid}")->assertForbidden();
});

test('atualizar status preenche handled_by e handled_at automaticamente', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $interest = EnrollmentInterest::factory()->create();

    $response = $this->actingAs($user)->patchJson("/api/v1/enrollment-interests/{$interest->uuid}/status", [
        'status' => 'in_progress',
        'internal_note' => 'Aguardando retorno da família.',
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'in_progress');

    $fresh = $interest->fresh();
    expect($fresh->handled_by)->toBe($user->id)
        ->and($fresh->handled_at)->not->toBeNull()
        ->and($fresh->internal_note)->toBe('Aguardando retorno da família.');
});

test('comunicacao recebe 403 ao atualizar status', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $interest = EnrollmentInterest::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/enrollment-interests/{$interest->uuid}/status", [
        'status' => 'done',
    ])->assertForbidden();
});

test('status inválido é rejeitado', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $interest = EnrollmentInterest::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/enrollment-interests/{$interest->uuid}/status", [
        'status' => 'nao-existe',
    ])->assertStatus(422);
});
