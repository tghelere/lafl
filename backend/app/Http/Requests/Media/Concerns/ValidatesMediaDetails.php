<?php

declare(strict_types=1);

namespace App\Http\Requests\Media\Concerns;

use App\Actions\Media\Data\MediaDetailsData;

trait ValidatesMediaDetails
{
    /**
     * `alt` obrigatório em toda imagem, validado na API (docs/arquitetura.md, SEO) — e não
     * pode ser só espaço. `depicts_assisted_minor` também é obrigatório, sem padrão: é uma
     * declaração, e quem sobe a imagem precisa responder.
     *
     * @return array<string, list<string>>
     */
    protected function detailRules(): array
    {
        return [
            'alt' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'depicts_assisted_minor' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function detailMessages(): array
    {
        return [
            'alt.required' => 'Descreva a imagem para quem não pode vê-la (texto alternativo).',
            'alt.max' => 'O texto alternativo pode ter até 255 caracteres.',
            'caption.max' => 'A legenda pode ter até 500 caracteres.',
            'depicts_assisted_minor.required' => 'Informe se a imagem mostra criança ou adolescente atendido pela instituição.',
            'depicts_assisted_minor.boolean' => 'Informe se a imagem mostra criança ou adolescente atendido pela instituição.',
        ];
    }

    public function toDetailsDto(): MediaDetailsData
    {
        return new MediaDetailsData(
            alt: trim($this->string('alt')->value()),
            caption: $this->filled('caption') ? trim($this->string('caption')->value()) : null,
            depictsAssistedMinor: $this->boolean('depicts_assisted_minor'),
        );
    }
}
