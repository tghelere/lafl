<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item da listagem pública de páginas: slug e data de alteração, nada além disso.
 *
 * `updated_at` aparece aqui — e não em App\Http\Resources\Public\PageResource — porque é o
 * dado que o `<lastmod>` do sitemap precisa. Continua não sendo timestamp de auditoria: diz
 * quando o TEXTO PÚBLICO mudou, o que qualquer visitante infere lendo a página.
 *
 * @mixin Page
 */
final class PageListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
