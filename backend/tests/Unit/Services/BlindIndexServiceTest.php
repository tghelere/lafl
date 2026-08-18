<?php

declare(strict_types=1);

use App\Services\BlindIndexService;
use App\Support\StringNormalizer;

test('é determinístico: mesmo valor normalizado gera sempre o mesmo hash', function (): void {
    $service = app(BlindIndexService::class);
    $normalized = StringNormalizer::normalize('Maria da Silva');

    expect($service->hash($normalized))->toBe($service->hash($normalized));
});

test('produz um hash hexadecimal de 64 caracteres (SHA-256)', function (): void {
    $service = app(BlindIndexService::class);

    expect($service->hash('qualquer valor'))->toMatch('/^[0-9a-f]{64}$/');
});

test('valores normalizados diferentes geram hashes diferentes', function (): void {
    $service = app(BlindIndexService::class);

    expect($service->hash(StringNormalizer::normalize('Maria da Silva')))
        ->not->toBe($service->hash(StringNormalizer::normalize('Maria de Souza')));
});

test('é sensível à chave: trocar BLIND_INDEX_KEY muda o hash do mesmo valor', function (): void {
    $normalized = StringNormalizer::normalize('Maria da Silva');

    config(['data-protection.blind_index_key' => 'base64:'.base64_encode(random_bytes(32))]);
    $hashA = app(BlindIndexService::class)->hash($normalized);

    config(['data-protection.blind_index_key' => 'base64:'.base64_encode(random_bytes(32))]);
    $hashB = app(BlindIndexService::class)->hash($normalized);

    expect($hashA)->not->toBe($hashB);
});
