<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\ContentMarker;
use App\Services\InstitutionalFacts;

/**
 * Os marcadores disponíveis com o valor que cada um tem AGORA, para o editor de páginas do
 * painel mostrar a quem escreve.
 *
 * O valor atual vem junto de propósito: sem ele, `{{documentos_transparencia}}` é um nome
 * sem significado para quem nunca viu o resultado. Com ele, a pessoa sabe que vai sair "71
 * documentos" antes de escrever a frase em volta.
 *
 * Lista fechada e curta (é um enum), por isso sem paginação — mesmo caso de
 * App\Http\Controllers\Api\V1\RoleController.
 */
final class ListContentMarkers
{
    public function __construct(private readonly InstitutionalFacts $facts) {}

    /**
     * @return list<array{name: string, marker: string, label: string, value: string}>
     */
    public function handle(): array
    {
        return array_map(fn (ContentMarker $marker): array => [
            'name' => $marker->value,
            'marker' => $marker->placeholder(),
            'label' => $marker->label(),
            'value' => $this->facts->value($marker),
        ], ContentMarker::cases());
    }
}
