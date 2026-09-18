<?php

declare(strict_types=1);

use App\Support\Transparency\DocumentSlug;

test('o slug base sai do título, sem acento e sem pontuação', function (): void {
    expect(DocumentSlug::base('Balanço patrimonial 2024'))->toBe('balanco-patrimonial-2024')
        ->and(DocumentSlug::base('Prestação de contas — CEI Anália Franco 2023'))
        ->toBe('prestacao-de-contas-cei-analia-franco-2023');
});

test('título sem nenhum caractere aproveitável vira um slug utilizável mesmo assim', function (): void {
    expect(DocumentSlug::base('— … !'))->toBe('documento');
});

test('título longo é cortado no hífen anterior ao limite, sem partir palavra', function (): void {
    $slug = DocumentSlug::base(str_repeat('prestacao de contas ', 20));

    expect(mb_strlen($slug))->toBeLessThanOrEqual(120)
        ->and($slug)->toEndWith('contas')
        ->and($slug)->not->toEndWith('-');
});
