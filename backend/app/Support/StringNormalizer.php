<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalização única e centralizada usada por todo blind index (ver
 * docs/protecao-de-dados.md). Se dois pontos do sistema normalizarem de formas diferentes, o
 * índice quebra silenciosamente — por isso nenhum outro lugar do código deve reimplementar
 * esta lógica.
 */
final class StringNormalizer
{
    /**
     * Minúsculas, sem acento, espaços colapsados e sem espaço nas pontas.
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = Str::ascii($value);

        return mb_strtolower($value);
    }
}
