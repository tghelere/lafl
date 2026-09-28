<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ProgramApplication;
use Illuminate\Support\Facades\DB;

function programApplicationPayload(array $overrides = []): array
{
    return [...[
        'guardian_name' => 'João Pereira',
        'phone' => '(43) 98888-0000',
        'consent' => true,
    ], ...$overrides];
}

test('envio válido cria o registro e devolve só uuid e data', function (): void {
    $response = $this->postJson('/api/v1/public/program-applications', programApplicationPayload());

    $response->assertCreated()->assertJsonStructure(['data' => ['uuid', 'created_at']]);

    expect(ProgramApplication::count())->toBe(1);

    $application = ProgramApplication::first();
    expect($application->guardian_name)->toBe('João Pereira')
        ->and($application->phone)->toBe('(43) 98888-0000')
        ->and($application->status->value)->toBe('in_progress')
        ->and($application->expires_at->diffInDays(now(), true))->toBeGreaterThan(300);
});

test('dado pessoal nunca é gravado em texto puro', function (): void {
    $this->postJson('/api/v1/public/program-applications', programApplicationPayload())->assertCreated();

    $raw = DB::table('program_applications')->first();

    expect($raw->guardian_name)->not->toContain('João Pereira')
        ->and($raw->phone)->not->toContain('98888-0000');
});

test('sem nome é rejeitado', function (): void {
    $payload = programApplicationPayload();
    unset($payload['guardian_name']);

    $this->postJson('/api/v1/public/program-applications', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('guardian_name');
});

test('sem telefone é rejeitado', function (): void {
    $payload = programApplicationPayload();
    unset($payload['phone']);

    $this->postJson('/api/v1/public/program-applications', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');
});

test('sem consentimento é rejeitado', function (): void {
    $this->postJson('/api/v1/public/program-applications', programApplicationPayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');

    expect(ProgramApplication::count())->toBe(0);
});

test('honeypot preenchido devolve sucesso mas não grava nada', function (): void {
    $response = $this->postJson('/api/v1/public/program-applications', programApplicationPayload(['website' => 'https://bot.example']));

    $response->assertCreated();
    expect(ProgramApplication::count())->toBe(0);
});

test('direcao e contraturno podem ver o formulário, atendimento comunicacao e bazar não', function (): void {
    expect(userWithRole(Role::Direcao->value)->can('viewAny', ProgramApplication::class))->toBeTrue()
        ->and(userWithRole(Role::Contraturno->value)->can('viewAny', ProgramApplication::class))->toBeTrue()
        ->and(userWithRole(Role::Atendimento->value)->can('viewAny', ProgramApplication::class))->toBeFalse()
        ->and(userWithRole(Role::Comunicacao->value)->can('viewAny', ProgramApplication::class))->toBeFalse()
        ->and(userWithRole(Role::Bazar->value)->can('viewAny', ProgramApplication::class))->toBeFalse();
});
