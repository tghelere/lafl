<?php

declare(strict_types=1);

/**
 * ÂNCORA DE COMPATIBILIDADE DO BLIND INDEX — NÃO REGENERE ESTES VALORES.
 *
 * Os hashes abaixo são a garantia de que App\Support\StringNormalizer::normalize()
 * nunca muda de comportamento silenciosamente (ver
 * docs/decisoes/0001-normalizacao-blind-index-autocontida.md). Se este teste falhar,
 * a causa é uma mudança na normalização — NUNCA "conserte" recalculando os hashes
 * esperados para fazer o teste passar. Isso destrói exatamente a proteção que o teste
 * existe para dar. Se a normalização precisa mudar de fato, a base já gravada precisa
 * de re-hash, e essa decisão é do time, não do CI.
 *
 * A chave HMAC abaixo é fixa e exclusiva deste teste — não é a chave de produção
 * (config('data-protection.blind_index_key')), para que o teste nunca quebre por
 * rotação de chave real.
 */

use App\Support\StringNormalizer;

const GOLDEN_HMAC_KEY_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcd';

function goldenHash(string $value): string
{
    return hash_hmac('sha256', StringNormalizer::normalize($value), hex2bin(GOLDEN_HMAC_KEY_HEX));
}

test('acento', function (): void {
    expect(goldenHash('café'))
        ->toBe('1df5d47d51742684b2857cdc7d4014dd2a72d1388a47c2af5dc5eba1e6d72ae6');
});

test('cedilha', function (): void {
    expect(goldenHash('ação'))
        ->toBe('6e56ea7c537129dccb0cf4f897eb526600176384ffa0de6b1771f935e0c55bd2');
});

test('trema', function (): void {
    expect(goldenHash('ü'))
        ->toBe('1c2c918327c35f6942a29d1ebca2075efe252fbf9b8f79f3f8f06beb5a6934f7');
});

test('maiuscula', function (): void {
    expect(goldenHash('MARIA'))
        ->toBe('db645ceb9cfcb4facd492795b98de681786677b4a617b2e36c9ac20d40d11524');
});

test('espaco multiplo colapsado', function (): void {
    expect(goldenHash('maria   silva'))
        ->toBe('7ab1b6d81adfcf639124b45158d943f71c61053f52aadf5a3ef2bdc7f60b79d2');
});

test('espaco nas pontas', function (): void {
    expect(goldenHash('  maria  '))
        ->toBe('db645ceb9cfcb4facd492795b98de681786677b4a617b2e36c9ac20d40d11524');
});

test('string vazia', function (): void {
    expect(goldenHash(''))
        ->toBe('76745a911e8ee93929b5da6410867ce29b1e7feda4eaa64525b0adc869be2a4e');
});

test('hifen', function (): void {
    expect(goldenHash('jean-paul'))
        ->toBe('537cdc20d814911335b608e05ba9733502347669b17ea3d6933a4a91c65803cb');
});

test('apostrofo', function (): void {
    expect(goldenHash("d'avila"))
        ->toBe('bd46db2ced565711e2d1f5a07150503c9083b32b87f6bd5d3918e67807664eb3');
});

test('numero', function (): void {
    expect(goldenHash('unidade2'))
        ->toBe('0b65aa073c8216242bd0897545ba5793dc5355d18fd6eeba76efff0e9a6edd57');
});

test('NFC e NFD do mesmo valor produzem o mesmo hash', function (): void {
    $nfc = "caf\u{00E9}";
    $nfd = "cafe\u{0301}";

    expect(goldenHash($nfc))
        ->toBe(goldenHash($nfd))
        ->toBe('1df5d47d51742684b2857cdc7d4014dd2a72d1388a47c2af5dc5eba1e6d72ae6');
});
