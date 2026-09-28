<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\VolunteerApplication;
use Spatie\Activitylog\Models\Activity;

/**
 * Leitura compartilhada pela equipe, separada do status de atendimento (ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md).
 */
test('abrir o detalhe marca como lido e registra quem abriu', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    expect($message->read_at)->toBeNull();

    $response = $this->actingAs($user)->getJson("/api/v1/contact-messages/{$message->uuid}");

    $response->assertOk()
        ->assertJsonPath('data.is_read', true)
        ->assertJsonPath('data.read_by', $user->name);

    $fresh = $message->fresh();
    expect($fresh->read_at)->not->toBeNull()
        ->and($fresh->read_by)->toBe($user->id);
});

/**
 * A leitura é da EQUIPE, não de cada pessoa: quem abre depois vê o registro já lido, com o nome
 * de quem abriu primeiro. É o ponto central da decisão — uma leitura por usuário faria cada
 * pessoa ver a mesma mensagem como nova.
 */
test('segunda pessoa a abrir vê o registro já lido, com o nome da primeira', function (): void {
    $primeira = userWithRole(Role::Direcao->value);
    $segunda = userWithRole(Role::Atendimento->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($primeira)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    $leituraOriginal = $message->fresh()->read_at;

    $this->actingAs($segunda)->getJson("/api/v1/contact-messages/{$message->uuid}")
        ->assertOk()
        ->assertJsonPath('data.read_by', $primeira->name);

    // E não é sobrescrito: `read_at` é a PRIMEIRA leitura, não a última — "último acesso" é
    // assunto do log de auditoria.
    expect($message->fresh()->read_at?->toIso8601String())->toBe($leituraOriginal?->toIso8601String())
        ->and($message->fresh()->read_by)->toBe($primeira->id);
});

test('marcar como não lido limpa a leitura para a equipe inteira', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    $this->actingAs($user)->deleteJson("/api/v1/contact-messages/{$message->uuid}/read")
        ->assertOk()
        ->assertJsonPath('data.is_read', false)
        ->assertJsonPath('data.read_at', null)
        ->assertJsonPath('data.read_by', null);

    $fresh = $message->fresh();
    expect($fresh->read_at)->toBeNull()
        ->and($fresh->read_by)->toBeNull();
});

/**
 * Esconder da equipe que o registro já havia sido aberto é ato deliberado — e `read_at` está
 * fora do `logOnly` do model de propósito (ver App\Models\Concerns\IsFormSubmission), então sem
 * o `activity()` da Action isto não deixaria rastro nenhum.
 */
test('marcar como não lido fica registrado na auditoria', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();
    $this->actingAs($user)->deleteJson("/api/v1/contact-messages/{$message->uuid}/read")->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'forms')
        ->where('event', 'marked_unread')
        ->where('subject_type', ContactMessage::class)
        ->where('subject_id', $message->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id);
});

/**
 * A primeira abertura registra o acesso (`viewed`) e nada além disso. Se `read_at` entrasse no
 * `logOnly` do model, a escrita da leitura geraria também um `updated`, e a tela de Auditoria
 * mostraria duas linhas para um acesso só. Ver o docblock de
 * App\Models\Concerns\IsFormSubmission::getActivitylogOptions.
 */
test('a primeira leitura registra o acesso, e não também uma alteração', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $message = ContactMessage::factory()->create();

    $antes = Activity::query()
        ->where('subject_type', ContactMessage::class)
        ->where('subject_id', $message->id)
        ->pluck('event');

    $this->actingAs($user)->getJson("/api/v1/contact-messages/{$message->uuid}")->assertOk();

    $depois = Activity::query()
        ->where('subject_type', ContactMessage::class)
        ->where('subject_id', $message->id)
        ->pluck('event');

    expect($depois->diff($antes)->values()->all())->toBe(['viewed'])
        ->and($depois)->not->toContain('updated');
});

test('comunicacao recebe 403 ao marcar como não lido', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $message = ContactMessage::factory()->create(['read_at' => now()]);

    $this->actingAs($user)->deleteJson("/api/v1/contact-messages/{$message->uuid}/read")
        ->assertForbidden();

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('não autenticado recebe 401 ao marcar como não lido', function (): void {
    $message = ContactMessage::factory()->create(['read_at' => now()]);

    $this->deleteJson("/api/v1/contact-messages/{$message->uuid}/read")->assertUnauthorized();
});

test('listagem filtra por não lidos e por lidos', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $naoLido = ContactMessage::factory()->create();
    $lido = ContactMessage::factory()->create(['read_at' => now(), 'read_by' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages?read=unread');
    expect($response->json('data.*.uuid'))->toBe([$naoLido->uuid]);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages?read=read');
    expect($response->json('data.*.uuid'))->toBe([$lido->uuid]);
});

test('filtro de leitura com valor desconhecido responde 422', function (): void {
    $user = userWithRole(Role::Direcao->value);

    $this->actingAs($user)->getJson('/api/v1/contact-messages?read=talvez')
        ->assertStatus(422)
        ->assertJsonValidationErrors('read');
});

/**
 * Leitura e status são independentes — é por isso que os dois existem. Um registro concluído
 * pode estar não lido (alguém resolveu por telefone e marcou como concluído pela listagem sem
 * abrir), e um registro em atendimento pode estar lido.
 */
test('leitura e status de atendimento não se contaminam', function (): void {
    $user = userWithRole(Role::Bazar->value);
    $request = PickupRequest::factory()->done()->create();

    expect($request->read_at)->toBeNull();

    $this->actingAs($user)->getJson("/api/v1/pickup-requests/{$request->uuid}")
        ->assertOk()
        ->assertJsonPath('data.is_read', true)
        ->assertJsonPath('data.status', 'done');

    $this->actingAs($user)->deleteJson("/api/v1/pickup-requests/{$request->uuid}/read")
        ->assertOk()
        ->assertJsonPath('data.is_read', false)
        ->assertJsonPath('data.status', 'done');
});

test('a listagem diz se cada registro foi lido, sem expor quem leu', function (): void {
    $user = userWithRole(Role::Direcao->value);
    ContactMessage::factory()->create(['read_at' => now(), 'read_by' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/contact-messages');

    $response->assertOk()->assertJsonPath('data.0.is_read', true);

    // Quem leu e quando são informação do detalhe, que é auditado — a listagem não precisa
    // disso e não é auditada por registro.
    expect($response->json('data.0'))->not->toHaveKey('read_by')
        ->and($response->json('data.0'))->not->toHaveKey('read_at');
});

test('as cinco entidades respondem ao endpoint de não lido', function (string $resource, string $model): void {
    $user = userWithRole(Role::SuperAdmin->value);
    $submission = $model::factory()->create(['read_at' => now()]);

    $this->actingAs($user)->deleteJson("/api/v1/{$resource}/{$submission->uuid}/read")
        ->assertOk()
        ->assertJsonPath('data.is_read', false);
})->with([
    ['program-applications', ProgramApplication::class],
    ['pickup-requests', PickupRequest::class],
    ['volunteer-applications', VolunteerApplication::class],
    ['partnership-inquiries', PartnershipInquiry::class],
    ['contact-messages', ContactMessage::class],
]);
