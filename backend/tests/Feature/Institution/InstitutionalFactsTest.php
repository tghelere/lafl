<?php

declare(strict_types=1);

use App\Enums\ContentMarker;
use App\Models\TransparencyDocument;
use App\Services\InstitutionalFacts;
use Carbon\CarbonImmutable;

function facts(): InstitutionalFacts
{
    // Instância nova a cada chamada, nunca a do container: o serviço memoriza o que calcula
    // (é singleton por requisição), e um teste que publica um documento depois de já ter lido
    // a contagem leria o valor velho.
    return new InstitutionalFacts;
}

test('idade com data completa só avança no dia do aniversário', function (): void {
    config(['institution.milestones' => ['association_founded' => '1953-07-12']]);

    $this->travelTo(CarbonImmutable::parse('2026-07-11 12:00:00', 'America/Sao_Paulo'));
    expect(facts()->ageInYears('association_founded'))->toBe(72);

    $this->travelTo(CarbonImmutable::parse('2026-07-12 00:01:00', 'America/Sao_Paulo'));
    expect(facts()->ageInYears('association_founded'))->toBe(73);

    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:59:00', 'America/Sao_Paulo'));
    expect(facts()->ageInYears('association_founded'))->toBe(73);
});

test('idade de marco com só o ano sai por diferença de ano', function (): void {
    config(['institution.milestones' => ['bazaar_opened' => '1968']]);

    // A imprecisão é conhecida e deliberada (ver o docblock de InstitutionalFacts::
    // computeAges): sem o dia, 1º de janeiro já conta o ano inteiro.
    $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00', 'America/Sao_Paulo'));
    expect(facts()->ageInYears('bazaar_opened'))->toBe(58);

    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:59:00', 'America/Sao_Paulo'));
    expect(facts()->ageInYears('bazaar_opened'))->toBe(58);
});

test('a idade vira no dia de Londrina, não no de UTC', function (): void {
    config(['institution.milestones' => ['association_founded' => '1953-07-12']]);

    // 12/07 às 02h em UTC ainda é 11/07 às 23h em Londrina — o aniversário não chegou.
    $this->travelTo(CarbonImmutable::parse('2026-07-12 02:00:00', 'UTC'));

    expect(now()->toDateString())->toBe('2026-07-12')
        ->and(facts()->ageInYears('association_founded'))->toBe(72);
});

test('idade formatada acerta o singular, o plural e o zero', function (): void {
    expect(facts()->formatYears(0))->toBe('0 anos')
        ->and(facts()->formatYears(1))->toBe('1 ano')
        ->and(facts()->formatYears(2))->toBe('2 anos')
        ->and(facts()->formatYears(73))->toBe('73 anos');
});

test('contagem formatada acerta o singular, o plural e o milhar', function (): void {
    expect(facts()->formatDocuments(0))->toBe('0 documentos')
        ->and(facts()->formatDocuments(1))->toBe('1 documento')
        ->and(facts()->formatDocuments(2))->toBe('2 documentos')
        ->and(facts()->formatDocuments(71))->toBe('71 documentos')
        ->and(facts()->formatDocuments(1234))->toBe('1.234 documentos');
});

test('a contagem de documentos acompanha publicar, despublicar e excluir', function (): void {
    expect(facts()->publishedTransparencyDocumentCount())->toBe(0);

    $documento = TransparencyDocument::factory()->published()->create();
    TransparencyDocument::factory()->published()->create();
    TransparencyDocument::factory()->create(); // rascunho, nunca conta

    expect(facts()->publishedTransparencyDocumentCount())->toBe(2);

    $documento->update(['published_at' => null]);
    expect(facts()->publishedTransparencyDocumentCount())->toBe(1);

    $documento->update(['published_at' => now()]);
    expect(facts()->publishedTransparencyDocumentCount())->toBe(2);

    // Exclusão é soft delete (ver App\Actions\Transparency\DeleteTransparencyDocument) — o
    // registro continua na tabela, e mesmo assim não pode entrar na conta.
    $documento->delete();
    expect(facts()->publishedTransparencyDocumentCount())->toBe(1);
});

test('todo marcador tem valor formatado, e o de documentos conta o acervo', function (): void {
    TransparencyDocument::factory()->published()->create();

    $valores = facts()->values();

    expect(array_keys($valores))
        ->toBe(array_map(static fn (ContentMarker $marker): string => $marker->value, ContentMarker::cases()))
        ->and($valores['documentos_transparencia'])->toBe('1 documento')
        ->and($valores['idade_associacao'])->toMatch('/^\d+ anos?$/')
        ->and($valores['idade_bazar'])->toMatch('/^\d+ anos?$/');
});

test('os marcos publicados trazem ano, data quando conhecida, e idade crua e formatada', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));

    $marcos = facts()->milestones();

    expect($marcos['association_founded'])->toBe([
        'year' => 1953,
        'date' => '1953-07-12',
        'age_years' => 73,
        'age_formatted' => '73 anos',
    ])->and($marcos['bazaar_opened'])->toBe([
        'year' => 1968,
        // Sem dia confirmado, o endpoint não inventa um — quem consome sabe que só há o ano.
        'date' => null,
        'age_years' => 58,
        'age_formatted' => '58 anos',
    ]);
});

test('marco desconhecido não passa despercebido', function (): void {
    expect(fn (): int => facts()->ageInYears('nao_existe'))
        ->toThrow(InvalidArgumentException::class);
});

test('marco com formato inválido no config falha alto', function (): void {
    config(['institution.milestones' => ['association_founded' => '12/07/1953']]);

    expect(fn (): int => facts()->ageInYears('association_founded'))
        ->toThrow(RuntimeException::class);
});
