<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Blind index para igualdade exata (documento, nome normalizado) — ver
 * docs/protecao-de-dados.md. HMAC-SHA256 com chave dedicada, separada de APP_KEY e da chave
 * de criptografia de campo. Permite busca/checagem de duplicidade sem descriptografar nada.
 *
 * O valor de entrada deve já ter passado por App\Support\StringNormalizer — este serviço não
 * normaliza, só faz hash.
 */
final class BlindIndexService
{
    /**
     * HMAC-SHA256 em hexadecimal (64 caracteres) do valor já normalizado.
     */
    public function hash(string $normalizedValue): string
    {
        return hash_hmac('sha256', $normalizedValue, $this->resolveKey());
    }

    private function resolveKey(): string
    {
        $key = config('data-protection.blind_index_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'BLIND_INDEX_KEY não configurada. Ver .env.example e docs/protecao-de-dados.md.'
            );
        }

        return self::decodeKey($key);
    }

    public static function decodeKey(string $key): string
    {
        return str_starts_with($key, 'base64:')
            ? (base64_decode(substr($key, 7), true) ?: $key)
            : $key;
    }
}
