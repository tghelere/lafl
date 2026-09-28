<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\PickupRequest;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Tela de Auditoria dos formulários recebidos — `GET /api/v1/audit-logs` (sessão 25, item 3).
 */
test('não autenticado recebe 401', function (): void {
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
});

/**
 * Quem audita não pode ser quem é auditado: `direcao` aparece nas linhas do log. É a única
 * afirmação que faz o registro valer algo.
 */
test('nenhum papel de área acessa a auditoria — nem direcao', function (string $role): void {
    $user = userWithRole($role);

    $this->actingAs($user)->getJson('/api/v1/audit-logs')->assertForbidden();
})->with([
    Role::Direcao->value,
    Role::Atendimento->value,
    Role::Bazar->value,
    Role::Contraturno->value,
    Role::Financeiro->value,
    Role::Comunicacao->value,
]);

test('super_admin vê o acesso que outra pessoa fez, com autor, ação, tipo, registro e IP', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs?type=contact_message');

    $response->assertOk();

    $acesso = collect($response->json('data'))->firstWhere('event', 'viewed');

    expect($acesso)->not->toBeNull()
        ->and($acesso['user'])->toBe($direcao->name)
        ->and($acesso['action_label'])->toBe('Detalhe acessado')
        ->and($acesso['form_type'])->toBe('contact_message')
        ->and($acesso['form_type_label'])->toBe('Mensagem de contato')
        ->and($acesso['record_uuid'])->toBe($message->uuid)
        ->and($acesso['record_resource'])->toBe('contact-messages')
        ->and($acesso['ip'])->not->toBeNull()
        ->and($acesso['occurred_at_label'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
});

/**
 * A promessa central de docs/protecao-de-dados.md: "o log registra o acesso, nunca o valor
 * descriptografado". Uma tela de auditoria que mostrasse o diff do spatie seria a cópia em texto
 * puro que a criptografia existe para evitar.
 */
test('a auditoria nunca devolve valor de campo pessoal', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create([
        'name' => 'Beatriz Nogueira',
        'message' => 'Conteúdo confidencial da mensagem.',
    ]);

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();
    $this->actingAs($direcao)->patchJson("/api/v1/contact-messages/{$message->uuid}/status", [
        'status' => 'done',
        'internal_note' => 'Anotação interna que também não pode sair daqui.',
    ])->assertOk();

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs');

    $corpo = $response->assertOk()->content();

    expect($corpo)->not->toContain('Beatriz')
        ->and($corpo)->not->toContain('Conteúdo confidencial')
        ->and($corpo)->not->toContain('Anotação interna')
        // E nenhum id sequencial de linha de log no payload (CLAUDE.md, regra 4).
        ->and($response->json('data.0'))->not->toHaveKey('id');
});

test('filtra por tipo de formulário', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();
    $pickup = PickupRequest::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();
    $this->actingAs($direcao)->getJson("/api/v1/pickup-requests/{$pickup->uuid}")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs?type=pickup_request');

    $tipos = collect($response->json('data'))->pluck('form_type')->unique()->values();
    expect($tipos->all())->toBe(['pickup_request']);
});

test('filtra por usuário, pelo uuid da conta', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $atendimento = userWithRole(Role::Atendimento->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();
    $this->actingAs($atendimento)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson("/api/v1/audit-logs?user={$atendimento->uuid}");

    $autores = collect($response->json('data'))->pluck('user')->unique()->values();
    expect($autores->all())->toBe([$atendimento->name]);
});

/**
 * Um uuid de conta que não existe tem de virar 422, nunca filtro ignorado em silêncio — o
 * resultado disso seria devolver o log inteiro a quem pediu o de uma pessoa.
 */
test('usuário inexistente no filtro responde 422', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);

    $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs?user='.Str::uuid())
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

test('filtra por período no fuso de Londrina', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    // 28/09 23:30 em São Paulo — o UTC já está no dia 29.
    Activity::query()->latest('id')->first()->forceFill(['created_at' => '2026-09-29 02:30:00'])->save();

    $dentro = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs?from=2026-09-28&to=2026-09-28');
    expect(collect($dentro->json('data'))->pluck('occurred_at_label'))->toContain('28/09/2026 23:30');

    $fora = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs?from=2026-09-29&to=2026-09-29');
    expect(collect($fora->json('data'))->pluck('occurred_at_label'))->not->toContain('28/09/2026 23:30');
});

test('o histórico de um registro traz só os acontecimentos dele', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $alvo = ContactMessage::factory()->create();
    $outra = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$alvo->uuid}")->assertOk();
    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$outra->uuid}")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson("/api/v1/audit-logs?record={$alvo->uuid}");

    $registros = collect($response->json('data'))->pluck('record_uuid')->unique()->values();
    expect($registros->all())->toBe([$alvo->uuid]);
});

/**
 * Uuid que não corresponde a formulário nenhum tem de devolver lista vazia. Sem a guarda em
 * App\Actions\Audit\ListFormSubmissionAuditEntries, o filtro simplesmente não se aplicaria e a
 * resposta seria o log inteiro.
 */
test('registro inexistente devolve lista vazia, não o log inteiro', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs?record='.Str::uuid());

    expect($response->assertOk()->json('data'))->toBe([]);
});

/**
 * O log da mesma tabela também guarda login, logout e alteração de conta (logs `auth` e `users`).
 * Esta tela é a de formulário; misturar tudo daria uma tela que não responde pergunta nenhuma.
 */
test('a tela de formulário não mistura auditoria de conta', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);

    activity('users')
        ->causedBy($superAdmin)
        ->performedOn(User::factory()->create())
        ->event('role_changed')
        ->log('Papel alterado');

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs');

    expect($response->assertOk()->json('data'))->toBe([]);
});

test('a auditoria mostra quando alguém marcou um registro como não lido', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();
    $this->actingAs($direcao)->deleteJson("/api/v1/contact-messages/{$message->uuid}/read")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs');

    $entrada = collect($response->json('data'))->firstWhere('event', 'marked_unread');

    expect($entrada)->not->toBeNull()
        ->and($entrada['action_label'])->toBe('Marcado como não lido')
        ->and($entrada['user'])->toBe($direcao->name)
        ->and($entrada['ip'])->not->toBeNull();
});

test('a listagem vem da mais recente para a mais antiga', function (): void {
    $superAdmin = userWithRole(Role::SuperAdmin->value);
    $direcao = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($direcao)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();
    $this->actingAs($direcao)->deleteJson("/api/v1/contact-messages/{$message->uuid}/read")->assertOk();

    $response = $this->actingAs($superAdmin)->getJson('/api/v1/audit-logs');

    expect($response->json('data.0.event'))->toBe('marked_unread');
});
