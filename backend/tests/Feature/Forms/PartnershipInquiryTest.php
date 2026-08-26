<?php

declare(strict_types=1);

use App\Enums\PartnershipSupportType;
use App\Enums\Role;
use App\Models\PartnershipInquiry;
use App\Services\BlindIndexService;
use App\Support\StringNormalizer;
use Illuminate\Support\Facades\DB;

function partnershipInquiryPayload(array $overrides = []): array
{
    return [...[
        'company_name' => 'Empresa Exemplo Ltda',
        'tax_id' => '12.345.678/0001-90',
        'contact_name' => 'Ricardo Alves',
        'phone' => '(43) 95555-0000',
        'email' => 'contato@empresa.example.com',
        'support_type' => PartnershipSupportType::Financial->value,
        'message' => 'Gostaríamos de apoiar o contraturno.',
        'consent' => true,
    ], ...$overrides];
}

test('envio válido cria o registro e limpa a máscara do CNPJ', function (): void {
    $response = $this->postJson('/api/v1/public/partnership-inquiries', partnershipInquiryPayload());

    $response->assertCreated()->assertJsonStructure(['data' => ['uuid', 'created_at']]);

    expect(PartnershipInquiry::count())->toBe(1);

    $inquiry = PartnershipInquiry::first();
    expect($inquiry->company_name)->toBe('Empresa Exemplo Ltda')
        ->and($inquiry->tax_id)->toBe('12345678000190')
        ->and($inquiry->status->value)->toBe('new')
        ->and($inquiry->expires_at->diffInDays(now(), true))->toBeGreaterThan(1000);
});

test('blind index do cnpj é calculado e bate com o hash normalizado', function (): void {
    $this->postJson('/api/v1/public/partnership-inquiries', partnershipInquiryPayload())->assertCreated();

    $inquiry = PartnershipInquiry::first();
    $expectedHash = app(BlindIndexService::class)->hash(StringNormalizer::normalize('12345678000190'));

    expect($inquiry->tax_id_hash)->toBe($expectedHash);
});

test('dado pessoal, inclusive cnpj, nunca é gravado em texto puro', function (): void {
    $this->postJson('/api/v1/public/partnership-inquiries', partnershipInquiryPayload())->assertCreated();

    $raw = DB::table('partnership_inquiries')->first();

    expect($raw->contact_name)->not->toContain('Ricardo Alves')
        ->and($raw->tax_id)->not->toContain('12345678000190');
});

test('cnpj com menos de 14 dígitos é rejeitado', function (): void {
    $this->postJson('/api/v1/public/partnership-inquiries', partnershipInquiryPayload(['tax_id' => '123']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('tax_id');
});

test('sem consentimento é rejeitado', function (): void {
    $this->postJson('/api/v1/public/partnership-inquiries', partnershipInquiryPayload(['consent' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');
});

test('honeypot preenchido devolve sucesso mas não grava nada', function (): void {
    $response = $this->postJson('/api/v1/public/partnership-inquiries', partnershipInquiryPayload(['website' => 'https://bot.example']));

    $response->assertCreated();
    expect(PartnershipInquiry::count())->toBe(0);
});

test('direcao e atendimento podem ver o formulário, comunicacao e bazar não', function (): void {
    expect(userWithRole(Role::Direcao->value)->can('viewAny', PartnershipInquiry::class))->toBeTrue()
        ->and(userWithRole(Role::Atendimento->value)->can('viewAny', PartnershipInquiry::class))->toBeTrue()
        ->and(userWithRole(Role::Comunicacao->value)->can('viewAny', PartnershipInquiry::class))->toBeFalse()
        ->and(userWithRole(Role::Bazar->value)->can('viewAny', PartnershipInquiry::class))->toBeFalse();
});
