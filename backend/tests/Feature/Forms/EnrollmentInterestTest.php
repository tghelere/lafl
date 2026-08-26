<?php

declare(strict_types=1);

use App\Enums\ChildAgeRange;
use App\Enums\DesiredPeriod;
use App\Enums\Role;
use App\Models\EnrollmentInterest;
use Illuminate\Support\Facades\DB;

function enrollmentInterestPayload(array $overrides = []): array
{
    return [...[
        'guardian_name' => 'Maria da Silva',
        'phone' => '(43) 99999-0000',
        'email' => 'maria@example.com',
        'child_age_range' => ChildAgeRange::TresAnos->value,
        'desired_period' => DesiredPeriod::Integral->value,
        'message' => 'Gostaria de saber sobre vagas.',
        'consent' => true,
    ], ...$overrides];
}

test('envio válido cria o registro e devolve só uuid e data', function (): void {
    $response = $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload());

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['uuid', 'created_at']])
        ->assertJsonMissingPath('data.guardian_name')
        ->assertJsonMissingPath('data.email');

    expect(EnrollmentInterest::count())->toBe(1);

    $interest = EnrollmentInterest::first();
    expect($interest->guardian_name)->toBe('Maria da Silva')
        ->and($interest->status->value)->toBe('new')
        ->and($interest->consent_terms_version)->not->toBeEmpty()
        ->and($interest->consented_at)->not->toBeNull()
        ->and($interest->ip_hash)->toHaveLength(64)
        ->and($interest->expires_at->diffInDays(now(), true))->toBeGreaterThan(300);
});

test('dado pessoal nunca é gravado em texto puro', function (): void {
    $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload())->assertCreated();

    $raw = DB::table('enrollment_interests')->first();

    expect($raw->guardian_name)->not->toContain('Maria da Silva')
        ->and($raw->email)->not->toContain('maria@example.com');
});

test('sem consentimento é rejeitado', function (): void {
    $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');

    expect(EnrollmentInterest::count())->toBe(0);
});

test('faixa etária inválida é rejeitada', function (): void {
    $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload(['child_age_range' => 'dez_anos']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('child_age_range');
});

test('honeypot preenchido devolve sucesso mas não grava nada', function (): void {
    $response = $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload(['website' => 'https://bot.example']));

    $response->assertCreated()->assertJsonStructure(['data' => ['uuid', 'created_at']]);
    expect(EnrollmentInterest::count())->toBe(0);
});

test('rate limit por IP bloqueia excesso de envios', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload())->assertCreated();
    }

    $this->postJson('/api/v1/public/enrollment-interests', enrollmentInterestPayload())
        ->assertStatus(429);
});

test('direcao e atendimento podem ver o formulário, comunicacao e bazar não', function (): void {
    expect(userWithRole(Role::Direcao->value)->can('viewAny', EnrollmentInterest::class))->toBeTrue()
        ->and(userWithRole(Role::Atendimento->value)->can('viewAny', EnrollmentInterest::class))->toBeTrue()
        ->and(userWithRole(Role::Comunicacao->value)->can('viewAny', EnrollmentInterest::class))->toBeFalse()
        ->and(userWithRole(Role::Bazar->value)->can('viewAny', EnrollmentInterest::class))->toBeFalse();
});
