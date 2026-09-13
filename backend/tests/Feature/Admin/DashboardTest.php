<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;

test('não autenticado recebe 401', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('direcao vê pendências dos cinco tipos', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ProgramApplication::factory()->count(2)->create();
    ContactMessage::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type');

    expect($types)->toHaveCount(5)
        ->and($types)->toContain('program_application', 'contact_message', 'pickup_request');

    $programApplication = collect($response->json('data'))->firstWhere('type', 'program_application');
    expect($programApplication['pending'])->toBe(2);
});

test('comunicacao recebe zero de tudo — lista vazia', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    ProgramApplication::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('bazar só vê pendências de pickup_request', function (): void {
    $user = userWithRole(Role::Bazar->value);
    PickupRequest::factory()->create();
    ProgramApplication::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $types = collect($response->json('data'))->pluck('type');
    expect($types)->toHaveCount(1)
        ->and($types)->toContain('pickup_request');
});
