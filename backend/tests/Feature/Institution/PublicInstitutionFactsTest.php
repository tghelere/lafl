<?php

declare(strict_types=1);

use App\Models\TransparencyDocument;
use Carbon\CarbonImmutable;

test('endpoint público devolve marcos e acervo, crus e formatados', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));
    TransparencyDocument::factory()->published()->count(2)->create();
    TransparencyDocument::factory()->create(); // rascunho, não conta

    $this->getJson('/api/v1/public/institution-facts')
        ->assertOk()
        ->assertJsonPath('data.milestones.association_founded.year', 1953)
        ->assertJsonPath('data.milestones.association_founded.date', '1953-07-12')
        ->assertJsonPath('data.milestones.association_founded.age_years', 73)
        ->assertJsonPath('data.milestones.association_founded.age_formatted', '73 anos')
        ->assertJsonPath('data.milestones.bazaar_opened.year', 1968)
        ->assertJsonPath('data.milestones.bazaar_opened.date', null)
        ->assertJsonPath('data.milestones.bazaar_opened.age_formatted', '58 anos')
        ->assertJsonPath('data.transparency_documents.count', 2)
        ->assertJsonPath('data.transparency_documents.count_formatted', '2 documentos');
});

test('endpoint público de fatos não exige autenticação e revalida sempre', function (): void {
    $response = $this->getJson('/api/v1/public/institution-facts')->assertOk();

    expect($response->headers->get('cache-control'))->toContain('must-revalidate')
        ->and($response->headers->get('etag'))->not->toBeNull();
});

test('o ETag dos fatos muda quando o acervo muda', function (): void {
    $antes = $this->getJson('/api/v1/public/institution-facts')->headers->get('etag');

    TransparencyDocument::factory()->published()->create();

    expect($this->getJson('/api/v1/public/institution-facts')->headers->get('etag'))
        ->not->toBe($antes);
});
