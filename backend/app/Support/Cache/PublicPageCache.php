<?php

declare(strict_types=1);

namespace App\Support\Cache;

final class PublicPageCache
{
    public static function key(string $slug): string
    {
        return 'public-page:'.$slug;
    }
}
