<?php

declare(strict_types=1);

namespace App\Support\Media;

final class MediaVariants
{
    /**
     * Largura servida quando o endereço não diz qual (`/midia/{uuid}`, a forma canônica) — o
     * tamanho de uma imagem no corpo do texto em tela larga.
     */
    public const DEFAULT_WIDTH = 960;

    /**
     * A maior derivada — o destino do link de ampliação das imagens de conteúdo.
     *
     * @param  list<int>  $widths
     */
    public static function largest(array $widths): int
    {
        return self::pick($widths, PHP_INT_MAX);
    }

    /**
     * A derivada que atende a largura pedida: a maior que não passa dela, ou a menor que
     * existe. É o que mantém todo endereço de largura válido depois de uma substituição por
     * imagem menor — `/midia/{uuid}/1920.webp` indexado ou em cache continua respondendo, com a
     * maior que houver, em vez de 404.
     *
     * @param  list<int>  $widths
     */
    public static function pick(array $widths, int $requested): int
    {
        sort($widths);
        $fitting = array_filter($widths, static fn (int $w): bool => $w <= $requested);

        return $fitting !== [] ? max($fitting) : $widths[0];
    }
}
