<?php

declare(strict_types=1);

namespace App\Support;

use Normalizer;

/**
 * Normalização única e centralizada usada por todo blind index (ver
 * docs/protecao-de-dados.md e docs/decisoes/0001-normalizacao-blind-index-autocontida.md).
 * Se dois pontos do sistema normalizarem de formas diferentes, o índice quebra
 * silenciosamente — por isso nenhum outro lugar do código deve reimplementar esta lógica.
 *
 * A transliteração é uma tabela própria (não delega a nenhum pacote de terceiro): a tabela
 * de transliteração de uma dependência transitiva pode mudar num `composer update` e
 * invalidar silenciosamente todo blind index já gravado. Ver o ADR para o histórico
 * completo da decisão, incluindo por que a normalização Unicode (NFC) precisa rodar depois
 * do case-folding e imediatamente antes da tabela — não é um detalhe cosmético, é o que
 * evita que um acento sobreviva como caractere combinante e quebre o hash.
 */
final class StringNormalizer
{
    /**
     * Mapa de transliteração fixo para o que importa ao português: vogais acentuadas
     * minúsculas e cedilha. Qualquer caractere fora deste mapa passa inalterado (exceto
     * pelo case-folding do passo 3) — nunca é removido ou substituído por um placeholder,
     * para não colidir dois valores visualmente diferentes no mesmo hash.
     */
    private const TRANSLITERATION_MAP = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y',
    ];

    /**
     * Minúsculas, sem acento, espaços colapsados e sem espaço nas pontas.
     *
     * Ordem das operações (não reordenar sem ler o ADR): o NFC precisa rodar depois do
     * case-folding e o mais perto possível do strtr, porque o próprio case-folding pode
     * produzir saída decomposta (ex.: 'İ' -> 'i' + U+0307 combinante). Qualquer
     * transformação de string entre o NFC e o strtr pode desnormalizar de novo.
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value, 'UTF-8');
        $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;

        return strtr($value, self::TRANSLITERATION_MAP);
    }
}
