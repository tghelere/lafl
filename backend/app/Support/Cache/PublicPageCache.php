<?php

declare(strict_types=1);

namespace App\Support\Cache;

final class PublicPageCache
{
    /**
     * O número sobe quando muda o FORMATO do que fica guardado. Sem isso, a entrada gravada pela
     * versão anterior continuaria sendo servida por até dez minutos depois do deploy, sem os
     * campos novos. A v2 ganhou `images` (capa e galeria); a v3, o link de ampliação em volta de
     * cada imagem do texto e `full` nas imagens.
     */
    public static function key(string $slug): string
    {
        return 'public-page:v3:'.$slug;
    }
}
