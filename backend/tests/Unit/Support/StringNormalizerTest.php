<?php

declare(strict_types=1);

use App\Support\StringNormalizer;

test('normaliza minúsculas, acento e espaços colapsados', function (): void {
    expect(StringNormalizer::normalize('  José   DA  Silva  '))->toBe('jose da silva');
});

test('remove acentos de forma abrangente', function (): void {
    expect(StringNormalizer::normalize('ÀÉÎÕÜÇ ação'))->toBe('aeiouc acao');
});

test('é idempotente: normalizar o resultado não muda nada', function (): void {
    $once = StringNormalizer::normalize('  Maria   José  ');
    $twice = StringNormalizer::normalize($once);

    expect($twice)->toBe($once);
});

test('entradas visualmente diferentes mas semanticamente iguais normalizam igual', function (): void {
    expect(StringNormalizer::normalize('MARIA DA SILVA'))
        ->toBe(StringNormalizer::normalize('maria   da    silva'))
        ->toBe(StringNormalizer::normalize('Maria Da Silva'));
});
