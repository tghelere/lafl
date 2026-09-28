<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;

test('não autenticado recebe 401', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('direcao vê os cinco tipos, com a contagem de não lidos', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ProgramApplication::factory()->count(2)->create();
    ContactMessage::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type');

    expect($types)->toHaveCount(5)
        ->and($types)->toContain('program_application', 'contact_message', 'pickup_request');

    $programApplication = collect($response->json('data'))->firstWhere('type', 'program_application');
    expect($programApplication['unread'])->toBe(2)
        // O slug da rota do painel vem da API, não de um mapa reverso mantido à mão no
        // frontend (ver App\Actions\Dashboard\GetUnreadFormSubmissionCounts).
        ->and($programApplication['resource'])->toBe('program-applications');
});

/**
 * A troca que a sessão 25 fez: o contador era "status = new" e passou a ser "read_at nulo".
 * Um registro já lido mas sem nenhuma mudança de status deixa de ser contado — antes ele
 * continuaria aparecendo como pendência para sempre.
 */
test('registro lido sai da contagem sem precisar de mudança de status', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ContactMessage::factory()->create();
    $lido = ContactMessage::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/contact-messages/{$lido->uuid}")->assertOk();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $contactMessage = collect($response->json('data'))->firstWhere('type', 'contact_message');
    expect($contactMessage['unread'])->toBe(1);
});

test('comunicacao recebe zero de tudo — lista vazia', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    ProgramApplication::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('bazar só vê a contagem de pickup_request', function (): void {
    $user = userWithRole(Role::Bazar->value);
    PickupRequest::factory()->create();
    ProgramApplication::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

    $types = collect($response->json('data'))->pluck('type');
    expect($types)->toHaveCount(1)
        ->and($types)->toContain('pickup_request');
});
