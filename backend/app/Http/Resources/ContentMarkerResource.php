<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Um marcador de conteúdo do CMS como o painel precisa vê-lo (ver
 * App\Actions\Content\ListContentMarkers). `marker` é o que se escreve no texto, `value` é o
 * que sai no site hoje.
 *
 * @property-read array{name: string, marker: string, label: string, value: string} $resource
 */
final class ContentMarkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'],
            'marker' => $this->resource['marker'],
            'label' => $this->resource['label'],
            'value' => $this->resource['value'],
        ];
    }
}
