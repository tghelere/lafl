<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\PartnershipInquiry;
use Spatie\Activitylog\Models\Activity;

test('não autenticado recebe 401 ao listar', function (): void {
    $this->getJson('/api/v1/partnership-inquiries')->assertUnauthorized();
});

test('comunicacao recebe 403 ao listar', function (): void {
    $user = userWithRole(Role::Comunicacao->value);

    $this->actingAs($user)->getJson('/api/v1/partnership-inquiries')->assertForbidden();
});

test('direcao lista com dado mascarado, mas razão social em texto puro', function (): void {
    $user = userWithRole(Role::Direcao->value);
    PartnershipInquiry::factory()->create([
        'company_name' => 'Empresa Exemplo Ltda',
        'tax_id' => '12345678000190',
        'contact_name' => 'Ricardo Alves',
        'phone' => '43955550000',
        'email' => 'contato@empresa.example.com',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/partnership-inquiries');

    $response->assertOk();
    expect($response->json('data.0.company_name'))->toBe('Empresa Exemplo Ltda')
        ->and($response->json('data.0.tax_id'))->toBe('••••0190')
        ->and($response->json('data.0.contact_name'))->toBe('Ricardo')
        ->and($response->json('data.0.email'))->toBe('•••@empresa.example.com');
});

test('detalhe devolve dado completo e audita o acesso', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $inquiry = PartnershipInquiry::factory()->create(['tax_id' => '12345678000190']);

    $response = $this->actingAs($user)->getJson("/api/v1/partnership-inquiries/{$inquiry->uuid}");

    $response->assertOk()->assertJsonPath('data.tax_id', '12345678000190');

    $activity = Activity::where('log_name', 'forms')->where('event', 'viewed')->where('subject_id', $inquiry->id)->where('subject_type', PartnershipInquiry::class)->first();
    expect($activity)->not->toBeNull()
        ->and(json_encode($activity->properties))->not->toContain('12345678000190');
});

test('comunicacao recebe 403 no detalhe', function (): void {
    $user = userWithRole(Role::Comunicacao->value);
    $inquiry = PartnershipInquiry::factory()->create();

    $this->actingAs($user)->getJson("/api/v1/partnership-inquiries/{$inquiry->uuid}")->assertForbidden();
});

test('atualizar status preenche handled_by e handled_at automaticamente', function (): void {
    $user = userWithRole(Role::Direcao->value);
    $inquiry = PartnershipInquiry::factory()->create();

    $this->actingAs($user)->patchJson("/api/v1/partnership-inquiries/{$inquiry->uuid}/status", [
        'status' => 'done',
        'internal_note' => 'Parceria fechada.',
    ])->assertOk();

    $fresh = $inquiry->fresh();
    expect($fresh->handled_by)->toBe($user->id)
        ->and($fresh->internal_note)->toBe('Parceria fechada.');
});
