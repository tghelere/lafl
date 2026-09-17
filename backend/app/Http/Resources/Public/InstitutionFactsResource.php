<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use App\Services\InstitutionalFacts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Os mesmos números que os marcadores do CMS entregam, para as páginas do site cujo conteúdo
 * é fixo no .vue e por isso não passa pelo CMS (ver frontend-site/app/pages/index.vue).
 *
 * Cada valor vai cru E formatado: cru para quem precisa do número (ordenar, comparar),
 * formatado para quem só vai exibir — o front não monta a frase, senão o plural voltaria a
 * ser regra de negócio no frontend.
 *
 * @property-read InstitutionalFacts $resource
 */
final class InstitutionFactsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'milestones' => $this->resource->milestones(),
            'transparency_documents' => $this->resource->transparencyDocuments(),
        ];
    }
}
