<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\VolunteerApplication;
use Illuminate\Support\Facades\DB;

function contactMessagePayload(array $overrides = []): array
{
    return [...[
        'name' => 'Beatriz Nogueira',
        'email' => 'beatriz@example.com',
        'subject' => 'Dúvida sobre visitação',
        'message' => 'Gostaria de saber se é possível visitar a sede.',
        'consent' => true,
    ], ...$overrides];
}

test('envio válido cria o registro e devolve só uuid e data', function (): void {
    $response = $this->postJson('/api/v1/public/contact-messages', contactMessagePayload());

    $response->assertCreated()->assertJsonStructure(['data' => ['uuid', 'created_at']]);

    expect(ContactMessage::count())->toBe(1);

    $message = ContactMessage::first();
    expect($message->name)->toBe('Beatriz Nogueira')
        ->and($message->subject)->toBe('Dúvida sobre visitação')
        ->and($message->status->value)->toBe('in_progress')
        ->and($message->expires_at->diffInDays(now(), true))->toBeGreaterThan(150)
        ->and($message->expires_at->diffInDays(now(), true))->toBeLessThan(200);
});

test('dado pessoal nunca é gravado em texto puro', function (): void {
    $this->postJson('/api/v1/public/contact-messages', contactMessagePayload())->assertCreated();

    $raw = DB::table('contact_messages')->first();

    expect($raw->name)->not->toContain('Beatriz Nogueira')
        ->and($raw->message)->not->toContain('visitar a sede');
});

test('sem consentimento é rejeitado', function (): void {
    $this->postJson('/api/v1/public/contact-messages', contactMessagePayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');
});

test('honeypot preenchido devolve sucesso mas não grava nada', function (): void {
    $response = $this->postJson('/api/v1/public/contact-messages', contactMessagePayload(['website' => 'https://bot.example']));

    $response->assertCreated();
    expect(ContactMessage::count())->toBe(0);
});

test('direcao e atendimento podem ver o formulário, comunicacao e bazar não', function (): void {
    expect(userWithRole(Role::Direcao->value)->can('viewAny', ContactMessage::class))->toBeTrue()
        ->and(userWithRole(Role::Atendimento->value)->can('viewAny', ContactMessage::class))->toBeTrue()
        ->and(userWithRole(Role::Comunicacao->value)->can('viewAny', ContactMessage::class))->toBeFalse()
        ->and(userWithRole(Role::Bazar->value)->can('viewAny', ContactMessage::class))->toBeFalse();
});

test('comunicacao não acessa nenhum dos cinco formulários recebidos', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    expect($user->can('viewAny', ProgramApplication::class))->toBeFalse()
        ->and($user->can('viewAny', PickupRequest::class))->toBeFalse()
        ->and($user->can('viewAny', VolunteerApplication::class))->toBeFalse()
        ->and($user->can('viewAny', PartnershipInquiry::class))->toBeFalse()
        ->and($user->can('viewAny', ContactMessage::class))->toBeFalse();
});
