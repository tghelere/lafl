<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\VolunteerApplication;
use Illuminate\Support\Facades\DB;

function volunteerApplicationPayload(array $overrides = []): array
{
    return [...[
        'name' => 'Paula Lima',
        'phone' => '(43) 96666-0000',
        'email' => 'paula@example.com',
        'availability' => 'fins de semana',
        'interest_area' => 'bazar',
        'message' => 'Gostaria de ajudar aos sábados.',
        'consent' => true,
    ], ...$overrides];
}

test('envio válido cria o registro e devolve só uuid e data', function (): void {
    $response = $this->postJson('/api/v1/public/volunteer-applications', volunteerApplicationPayload());

    $response->assertCreated()->assertJsonStructure(['data' => ['uuid', 'created_at']]);

    expect(VolunteerApplication::count())->toBe(1);

    $application = VolunteerApplication::first();
    expect($application->name)->toBe('Paula Lima')
        ->and($application->status->value)->toBe('in_progress')
        ->and($application->expires_at->diffInDays(now(), true))->toBeGreaterThan(600);
});

test('dado pessoal nunca é gravado em texto puro', function (): void {
    $this->postJson('/api/v1/public/volunteer-applications', volunteerApplicationPayload())->assertCreated();

    $raw = DB::table('volunteer_applications')->first();

    expect($raw->name)->not->toContain('Paula Lima')
        ->and($raw->email)->not->toContain('paula@example.com');
});

test('sem consentimento é rejeitado', function (): void {
    $this->postJson('/api/v1/public/volunteer-applications', volunteerApplicationPayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');
});

test('honeypot preenchido devolve sucesso mas não grava nada', function (): void {
    $response = $this->postJson('/api/v1/public/volunteer-applications', volunteerApplicationPayload(['website' => 'https://bot.example']));

    $response->assertCreated();
    expect(VolunteerApplication::count())->toBe(0);
});

test('direcao e atendimento podem ver o formulário, comunicacao e bazar não', function (): void {
    expect(userWithRole(Role::Direcao->value)->can('viewAny', VolunteerApplication::class))->toBeTrue()
        ->and(userWithRole(Role::Atendimento->value)->can('viewAny', VolunteerApplication::class))->toBeTrue()
        ->and(userWithRole(Role::Comunicacao->value)->can('viewAny', VolunteerApplication::class))->toBeFalse()
        ->and(userWithRole(Role::Bazar->value)->can('viewAny', VolunteerApplication::class))->toBeFalse();
});
