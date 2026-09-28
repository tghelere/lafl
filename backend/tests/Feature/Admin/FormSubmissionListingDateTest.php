<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\PickupRequest;
use App\Support\InstitutionalTime;

/**
 * "Recebido em" e o fuso dos filtros De/Até das cinco listagens administrativas (ver
 * docs/relatorio-sessao-25.md, item 1).
 *
 * O fuso é o ponto do arquivo. O servidor grava em UTC e fica nos Estados Unidos (ver
 * docs/protecao-de-dados.md); quem filtra pensa no dia de Londrina. Entre 21h e meia-noite de
 * Londrina as duas contas divergem, e é exatamente essa faixa que os testes abaixo usam —
 * `whereDate` sobre a coluna UTC passaria em qualquer teste feito ao meio-dia e falharia em
 * produção às 22h.
 */
test('listagem devolve o rótulo de recebimento no fuso institucional', function (): void {
    $user = userWithRole(Role::Direcao->value);

    // 28/09/2026 23:30 em São Paulo = 29/09/2026 02:30 UTC. O rótulo tem de dizer 28, não 29.
    ContactMessage::factory()->create(['created_at' => '2026-09-29 02:30:00']);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages');

    $response->assertOk()->assertJsonPath('data.0.created_at_label', '28/09/2026 23:30');
});

test('detalhe devolve o mesmo rótulo de recebimento', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create(['created_at' => '2026-09-29 02:30:00']);

    $this->actingAs($user)->getJson("/api/v1/contact-messages/{$message->uuid}")
        ->assertOk()
        ->assertJsonPath('data.created_at_label', '28/09/2026 23:30');
});

test('filtro De inclui o registro das últimas horas do dia local', function (): void {
    $user = userWithRole(Role::Direcao->value);

    // 28/09 23:30 em São Paulo. Pelo UTC este registro é do dia 29.
    $fimDoDia28 = ContactMessage::factory()->create(['created_at' => '2026-09-29 02:30:00']);

    // 27/09 23:30 em São Paulo — véspera, tem de ficar de fora.
    ContactMessage::factory()->create(['created_at' => '2026-09-28 02:30:00']);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages?from=2026-09-28');

    $response->assertOk();
    expect($response->json('data.*.uuid'))->toBe([$fimDoDia28->uuid]);
});

test('filtro Até inclui o dia inteiro em São Paulo, não até a meia-noite do UTC', function (): void {
    $user = userWithRole(Role::Direcao->value);

    // 28/09 23:30 em São Paulo — dentro de "até 28/09" para quem filtra, apesar de o UTC já
    // estar no dia 29.
    $fimDoDia28 = ContactMessage::factory()->create(['created_at' => '2026-09-29 02:30:00']);

    // 29/09 00:30 em São Paulo — fora.
    ContactMessage::factory()->create(['created_at' => '2026-09-29 03:30:00']);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages?to=2026-09-28');

    $response->assertOk();
    expect($response->json('data.*.uuid'))->toBe([$fimDoDia28->uuid]);
});

test('filtro de data em formato desconhecido responde 422, não 500', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->getJson('/api/v1/contact-messages?from=ontem')
        ->assertStatus(422)
        ->assertJsonValidationErrors('from');
});

test('data final anterior à inicial responde 422', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->getJson('/api/v1/contact-messages?from=2026-09-28&to=2026-09-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

/**
 * Antes desta sessão a listagem de coletas ordenava por `scheduled_for` (agenda), e era a
 * única das cinco que não começava pelo que chegou por último. Ver o comentário em
 * App\Http\Controllers\Api\V1\PickupRequestController::index.
 */
test('listagem de coletas ordena da mais recente para a mais antiga', function (): void {
    $user = userWithRole(Role::Bazar->value);

    $antiga = PickupRequest::factory()->create([
        'created_at' => now()->subDays(2),
        'scheduled_for' => now()->addDay(),
    ]);
    $recente = PickupRequest::factory()->create([
        'created_at' => now(),
        'scheduled_for' => now()->addDays(10),
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/pickup-requests');

    $response->assertOk();
    expect($response->json('data.*.uuid'))->toBe([$recente->uuid, $antiga->uuid]);
});

test('o fuso do rótulo vem de config/institution.php, não de uma constante solta', function (): void {
    expect(InstitutionalTime::timezone())->toBe(config('institution.timezone'));
});
