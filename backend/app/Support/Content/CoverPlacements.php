<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Onde o site mostra a capa de cada página (App\Enums\PageImageRole::Cover). A capa não aparece
 * na própria página, e sim onde OUTRAS a usam para representá-la. Quem decide isso é o layout
 * do site (frontend-site/app/pages/index.vue e o-que-fazemos.vue). Este mapa é o espelho que a
 * API entrega ao painel, para quem edita a capa saber o que muda no site.
 *
 * Está no backend, e não no painel, pela regra 1 do CLAUDE.md: o painel não sabe nada do site
 * que não venha da API. Mudar onde o site usa uma capa exige mudar aqui também.
 */
final class CoverPlacements
{
    private const PLACES = [
        'quem-somos' => ['o destaque da página inicial'],
        'educacao-infantil' => ['o cartão de Educação infantil em "O que fazemos"'],
        'contraturno' => ['o cartão de Escola de contraturno em "O que fazemos"'],
        'bazar' => ['o cartão de Bazar beneficente em "O que fazemos"'],
    ];

    /**
     * @return list<string>
     */
    public static function for(string $slug): array
    {
        return self::PLACES[$slug] ?? [];
    }
}
