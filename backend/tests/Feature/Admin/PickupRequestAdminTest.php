<?php

declare(strict_types=1);

use App\Enums\FormSubmissionStatus;
use App\Enums\Role;
use App\Models\PickupRequest;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar', function (): void {
    $this->getJson('/api/v1/pickup-requests')->assertUnauthorized();
});

test('comunicacao recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $this->actingAs($user)->getJson('/api/v1/pickup-requests')->assertForbidden();
});

test('bazar lista pickup_requests normalmente', function (): void {
    $user = userWithRole(Role::Bazar->value);
    PickupRequest::factory()->create(['donor_name' => 'Carla Souza']);

    $response = $this->actingAs($user)->getJson('/api/v1/pickup-requests');

    $response->assertOk();
    expect($response->json('data.0.donor_name'))->toBe('Carla');
});

test('endereço nunca aparece na listagem, nem mascarado', function (): void {
    $user = userWithRole(Role::Direcao->value);
    PickupRequest::factory()->create(['address' => 'Rua das Flores, 123']);

    $response = $this->actingAs($user)->getJson('/api/v1/pickup-requests');

    $response->assertOk()->assertJsonMissingPath('data.0.address');
});

test('listagem filtra por status', function (): void {
    $user = userWithRole(Role::Direcao->value);
    PickupRequest::factory()->create(['status' => FormSubmissionStatus::New]);
    PickupRequest::factory()->done()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/pickup-requests?status=done');

    expect($response->json('data'))->toHaveCount(1);
});

test('detalhe devolve endereço completo e audita o acesso', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $request = PickupRequest::factory()->create(['address' => 'Rua das Flores, 123']);

    $response = $this->actingAs($user)->getJson("/api/v1/pickup-requests/{$request->uuid}");

    $response->assertOk()->assertJsonPath('data.address', 'Rua das Flores, 123');

    $activity = Activity::where('log_name', 'forms')->where('event', 'viewed')->where('subject_id', $request->id)->where('subject_type', PickupRequest::class)->first();
    expect($activity)->not->toBeNull()
        ->and(json_encode($activity->properties))->not->toContain('Rua das Flores');
});

test('bazar acessa o detalhe normalmente', function (): void {
    $user = userWithRole(Role::Bazar->value);
    $request = PickupRequest::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/pickup-requests/{$request->uuid}")->assertOk();
});

test('comunicacao recebe 403 no detalhe', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $request = PickupRequest::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/pickup-requests/{$request->uuid}")->assertForbidden();
});

test('bazar pode atualizar status', function (): void {
    $user = userWithRole(Role::Bazar->value);
    $request = PickupRequest::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/pickup-requests/{$request->uuid}/status", [
        'status' => 'done',
    ])->assertOk();

    $fresh = $request->fresh();
    expect($fresh->handled_by)->toBe($user->id)
        ->and($fresh->status)->toBe(FormSubmissionStatus::Done);
});
