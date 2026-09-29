<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Onde o site mostra a capa de cada página (App\Enums\PageImageRole::Cover). A capa não aparece
 * na própria página, e sim onde OUTRAS a usam para representá-la. Quem decide isso é o layout
 * do site (frontend-site/app/pages/index.vue e o-que-fazemos.vue). Este mapa é o espelho que a
 * API entrega ao painel, para quem edita a capa saber o que muda no site.
 *
 * Cada lugar vem com a preposição ("no destaque…"), pronto para a frase "A capa desta página
 * aparece …" da tela, que só junta os lugares.
 *
 * Está no backend, e não no painel, pela regra 1 do CLAUDE.md: o painel não sabe nada do site
 * que não venha da API. Mudar onde o site usa uma capa exige mudar aqui também.
 */
final class CoverPlacements
{
    private const PLACES = [
        'quem-somos' => ['no destaque da página inicial'],
        'educacao-infantil' => ['no cartão de Educação infantil, em "O que fazemos"'],
        'contraturno' => ['no cartão de Escola de contraturno, em "O que fazemos"'],
        'bazar' => ['no cartão de Bazar beneficente, em "O que fazemos"'],
    ];

    /**
     * @return list<string>
     */
    public static function for(string $slug): array
    {
        return self::PLACES[$slug] ?? [];
    }
}
