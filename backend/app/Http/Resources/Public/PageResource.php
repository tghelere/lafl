<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource público — nunca expõe autor, rascunho, status ou timestamp interno (ver
 * docs/estrutura-site.md, Parte 3).
 *
 * @mixin Page
 */
final class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }
}
