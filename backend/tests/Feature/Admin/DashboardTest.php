<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\EnrollmentInterest;
use App\Models\PickupRequest;

test('não autenticado recebe 401', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('direcao vê pendências dos seis tipos', function (): void {
    $user = userWithRole(Role::Direcao->value);
    EnrollmentInterest::factory()->count(2)->create();
    ContactMessage::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type');

    expect($types)->toHaveCount(6)
        ->and($types)->toContain('enrollment_interest', 'contact_message', 'pickup_request');

    $enrollment = collect($response->json('data'))->firstWhere('type', 'enrollment_interest');
    expect($enrollment['pending'])->toBe(2);
});

test('comunicacao recebe zero de tudo — lista vazia', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    EnrollmentInterest::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('bazar só vê pendências de pickup_request', function (): void {
    $user = userWithRole(Role::Bazar->value);
    PickupRequest::factory()->create();
    EnrollmentInterest::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $types = collect($response->json('data'))->pluck('type');
    expect($types)->toHaveCount(1)
        ->and($types)->toContain('pickup_request');
});
