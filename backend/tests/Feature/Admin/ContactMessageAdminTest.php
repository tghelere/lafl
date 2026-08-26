<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar', function (): void {
    $this->getJson('/api/v1/contact-messages')->assertUnauthorized();
});

test('comunicacao recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $this->actingAs($user)->getJson('/api/v1/contact-messages')->assertForbidden();
});

test('direcao lista com dado mascarado, mas assunto em texto puro', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ContactMessage::factory()->create([
        'name' => 'Beatriz Nogueira',
        'email' => 'beatriz@example.com',
        'subject' => 'Dúvida sobre visitação',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages');

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('Beatriz')
        ->and($response->json('data.0.email'))->toBe('•••@example.com')
        ->and($response->json('data.0.subject'))->toBe('Dúvida sobre visitação')
        ->and($response->json('data.0'))->not->toHaveKey('message');
});

test('detalhe devolve dado completo e audita o acesso', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create(['message' => 'Conteúdo confidencial.']);

    $response = $this->actingAs($user)->getJson("/api/v1/contact-messages/{$message->uuid}");

    $response->assertOk()->assertJsonPath('data.message', 'Conteúdo confidencial.');

    $activity = Activity::where('log_name', 'forms')->where('event', 'viewed')->where('subject_id', $message->id)->where('subject_type', ContactMessage::class)->first();
    expect($activity)->not->toBeNull()
        ->and(json_encode($activity->properties))->not->toContain('Conteúdo confidencial');
});

test('atualizar status preenche handled_by e handled_at automaticamente', function (): void {
    $user = userWithRole(Role::Atendimento->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/contact-messages/{$message->uuid}/status", [
        'status' => 'done',
    ])->assertOk();

    $fresh = $message->fresh();
    expect($fresh->handled_by)->toBe($user->id)
        ->and($fresh->handled_at)->not->toBeNull();
});
