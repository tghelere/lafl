<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\BlindIndexService;
use App\Support\StringNormalizer;

/**
 * Mantém campo cifrado e seu blind index de igualdade em sincronia automaticamente ao
 * salvar — ver docs/protecao-de-dados.md.
 *
 * Uso: o model declara `protected array $blindIndexes = ['name' => 'name_hash'];` (campo em
 * texto puro/decifrado em memória => coluna de hash). Este trait recalcula o hash a partir do
 * valor normalizado antes de cada save; não é preciso atribuir a coluna de hash manualmente.
 *
 * Cobre apenas o índice de igualdade (`{campo}_hash`). Busca por token
 * (`{campo}_tokens`, ver matriz de classificação) é uma extensão específica de domínio, a
 * implementar quando a entidade real existir.
 */
/**
 * O model que usa este trait deve declarar a property abaixo — não é declarada aqui porque
 * PHP considera fatal a redeclaração de uma property de trait com valor padrão diferente.
 *
 * @property array<string, string> $blindIndexes Mapa campo em texto puro => coluna de hash.
 */
trait HasBlindIndex
{
    public static function bootHasBlindIndex(): void
    {
        static::saving(function (self $model): void {
            $model->syncBlindIndexes();
        });
    }

    protected function syncBlindIndexes(): void
    {
        foreach ($this->blindIndexes as $field => $hashColumn) {
            $value = $this->getAttribute($field);

            $this->setAttribute(
                $hashColumn,
                $value === null ? null : app(BlindIndexService::class)->hash(StringNormalizer::normalize($value)),
            );
        }
    }
}
