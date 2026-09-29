<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * O papel de uma imagem da biblioteca numa página, fora do texto (App\Models\PageImage).
 *
 * - `Cover`: uma por página. Representa a página FORA dela: o cartão de "O que fazemos" usa a
 *   capa de cada frente, e o destaque da página inicial usa a capa de "Quem somos" (ver
 *   frontend-site/app/pages/index.vue e o-que-fazemos.vue). A página não mostra a própria capa.
 * - `Gallery`: as fotos em ordem que a própria página mostra depois do texto.
 *
 * Imagem inserida no meio do texto não tem linha aqui: ela vive no HTML do conteúdo, na forma
 * canônica `/midia/{uuid}` (ver docs/decisoes/0024-biblioteca-de-midia.md).
 */
enum PageImageRole: string
{
    case Cover = 'cover';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Cover => 'Capa',
            self::Gallery => 'Galeria',
        };
    }
}
