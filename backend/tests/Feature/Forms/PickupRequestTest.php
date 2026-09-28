<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Jobs\PurgeCompletedPickupRequestAddresses;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use Illuminate\Support\Facades\DB;

function pickupRequestPayload(array $overrides = []): array
{
    return [...[
        'donor_name' => 'Carla Souza',
        'phone' => '(43) 97777-0000',
        'address' => 'Rua das Flores, 123, Londrina/PR',
        'items_description' => 'Sofá de dois lugares e caixa de roupas.',
        'availability_window' => 'sábados de manhã',
        'consent' => true,
    ], ...$overrides];
}

test('envio válido cria o registro e devolve só uuid e data', function (): void {
    $response = $this->postJson('/api/v1/public/pickup-requests', pickupRequestPayload());

    $response->assertCreated()->assertJsonStructure(['data' => ['uuid', 'created_at']]);

    expect(PickupRequest::count())->toBe(1);

    $request = PickupRequest::first();
    expect($request->donor_name)->toBe('Carla Souza')
        ->and($request->address)->toBe('Rua das Flores, 123, Londrina/PR')
        ->and($request->status->value)->toBe('in_progress')
        // Retenção geral de 6 meses (rede de segurança), não 12 — ver App\Models\PickupRequest.
        ->and($request->expires_at->diffInDays(now(), true))->toBeGreaterThan(150)
        ->and($request->expires_at->diffInDays(now(), true))->toBeLessThan(200);
});

test('dado pessoal, inclusive endereço, nunca é gravado em texto puro', function (): void {
    $this->postJson('/api/v1/public/pickup-requests', pickupRequestPayload())->assertCreated();

    $raw = DB::table('pickup_requests')->first();

    expect($raw->donor_name)->not->toContain('Carla Souza')
        ->and($raw->address)->not->toContain('Rua das Flores');
});

test('sem consentimento é rejeitado', function (): void {
    $this->postJson('/api/v1/public/pickup-requests', pickupRequestPayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');
});

test('honeypot preenchido devolve sucesso mas não grava nada', function (): void {
    $response = $this->postJson('/api/v1/public/pickup-requests', pickupRequestPayload(['website' => 'https://bot.example']));

    $response->assertCreated();
    expect(PickupRequest::count())->toBe(0);
});

test('job de expurgo apaga endereço de coleta concluída, mas preserva o resto do registro', function (): void {
    $done = PickupRequest::factory()->done()->create(['donor_name' => 'Doadora Concluída']);
    $pending = PickupRequest::factory()->create(['donor_name' => 'Doadora Pendente']);

    (new PurgeCompletedPickupRequestAddresses)->handle();

    expect($done->fresh()->address)->toBeNull()
        ->and($done->fresh()->donor_name)->toBe('Doadora Concluída')
        ->and($pending->fresh()->address)->not->toBeNull();
});

test('direcao e bazar podem ver o formulário, atendimento e comunicacao não', function (): void {
    expect(userWithRole(Role::Direcao->value)->can('viewAny', PickupRequest::class))->toBeTrue()
        ->and(userWithRole(Role::Bazar->value)->can('viewAny', PickupRequest::class))->toBeTrue()
        ->and(userWithRole(Role::Atendimento->value)->can('viewAny', PickupRequest::class))->toBeFalse()
        ->and(userWithRole(Role::Comunicacao->value)->can('viewAny', PickupRequest::class))->toBeFalse();
});

test('bazar só acessa pickup_requests entre os formulários recebidos', function (): void {
    $bazar = userWithRole(Role::Bazar->value);

    expect($bazar->can('viewAny', PickupRequest::class))->toBeTrue()
        ->and($bazar->can('viewAny', ProgramApplication::class))->toBeFalse();
});
