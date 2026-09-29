<?php

declare(strict_types=1);

namespace App\Support\Cache;

final class PublicPageCache
{
    /**
     * O número sobe quando muda o FORMATO do que fica guardado. Sem isso, a entrada gravada pela
     * versão anterior continuaria sendo servida por até dez minutos depois do deploy, sem os
     * campos novos. A v2 ganhou `images` (capa e galeria).
     */
    public static function key(string $slug): string
    {
        return 'public-page:v2:'.$slug;
    }
}
