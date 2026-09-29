<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Support\Cache\PublicPageCache;
use Illuminate\Support\Facades\Cache;

/**
 * O HTML público de uma página é montado com as larguras e as dimensões da imagem de agora
 * (ver App\Actions\Media\ExpandContentImages) e cacheado por dez minutos. Trocar o arquivo ou
 * tirar a imagem do ar sem esquecer esse cache deixaria o site servindo o `srcset` antigo —
 * e, no caso da declaração de assistido, a imagem no ar por até dez minutos depois.
 */
final class ForgetPagesUsingMedia
{
    public function __construct(private readonly FindMediaUsages $usages) {}

    public function handle(Media $media): void
    {
        foreach ($this->usages->handle($media) as $page) {
            Cache::forget(PublicPageCache::key($page['slug']));
        }
    }
}
