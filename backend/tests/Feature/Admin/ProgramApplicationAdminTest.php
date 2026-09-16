<?php

declare(strict_types=1);

use App\Enums\FormSubmissionStatus;
use App\Enums\Role;
use App\Models\ProgramApplication;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar', function (): void {
    $this->getJson('/api/v1/program-applications')->assertUnauthorized();
});

test('comunicacao recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $this->actingAs($user)->getJson('/api/v1/program-applications')->assertForbidden();
});

test('direcao lista com dado mascarado', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ProgramApplication::factory()->create([
        'guardian_name' => 'João Pereira',
        'phone' => '43988880000',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/program-applications');

    $response->assertOk();
    expect($response->json('data.0.guardian_name'))->toBe('João')
        ->and($response->json('data.0.phone'))->toBe('••••0000');
});

test('listagem filtra por status', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ProgramApplication::factory()->create(['status' => FormSubmissionStatus::New]);
    ProgramApplication::factory()->create(['status' => FormSubmissionStatus::Discarded]);

    $response = $this->actingAs($user)->getJson('/api/v1/program-applications?status=discarded');

    expect($response->json('data'))->toHaveCount(1);
});

/**
 * whereDate('created_at', ...) é abstração do Laravel — a Grammar de cada driver já traduz
 * para a expressão certa (CAST no Postgres, date() no SQLite), mas o padrão se repete em
 * cinco controllers e nenhum tinha teste algum contra banco de verdade até a varredura de
 * dialeto desta sessão (ver docs/roadmap.md). Cobre aqui como representante do padrão —
 * ProgramApplication, PickupRequest, VolunteerApplication, PartnershipInquiry e
 * ContactMessage repetem o mesmo código, não a mesma consulta testada cinco vezes.
 */
test('listagem filtra por período (from/to) sobre created_at', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $foraDoPeriodo = ProgramApplication::factory()->create(['created_at' => now()->subDays(10)]);
    $dentroDoPeriodo = ProgramApplication::factory()->create(['created_at' => now()->subDays(3)]);
    $tambemForaDoPeriodo = ProgramApplication::factory()->create(['created_at' => now()]);

    $from = now()->subDays(5)->toDateString();
    $to = now()->subDays(1)->toDateString();

    $response = $this->actingAs($user)->getJson("/api/v1/program-applications?from={$from}&to={$to}");

    $response->assertOk();
    expect($response->json('data.*.uuid'))->toBe([$dentroDoPeriodo->uuid]);
});

test('detalhe devolve dado completo e audita o acesso', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $application = ProgramApplication::factory()->create(['guardian_name' => 'João Pereira']);

    $response = $this->actingAs($user)->getJson("/api/v1/program-applications/{$application->uuid}");

    $response->assertOk()->assertJsonPath('data.guardian_name', 'João Pereira');

    expect(Activity::where('log_name', 'forms')->where('event', 'viewed')->where('subject_id', $application->id)->where('subject_type', ProgramApplication::class)->exists())->toBeTrue();
});

test('comunicacao recebe 403 no detalhe', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $application = ProgramApplication::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/program-applications/{$application->uuid}")->assertForbidden();
});

test('atualizar status preenche handled_by e handled_at automaticamente', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $application = ProgramApplication::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/program-applications/{$application->uuid}/status", [
        'status' => 'done',
    ])->assertOk();

    $fresh = $application->fresh();
    expect($fresh->handled_by)->toBe($user->id)
        ->and($fresh->handled_at)->not->toBeNull();
});

test('comunicacao recebe 403 ao atualizar status', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $application = ProgramApplication::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/program-applications/{$application->uuid}/status", [
        'status' => 'done',
    ])->assertForbidden();
});
