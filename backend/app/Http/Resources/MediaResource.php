<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use App\Support\Media\MediaUrl;
use App\Support\Media\MediaVariants;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource administrativo da biblioteca.
 *
 * `preview_url` é a rota AUTENTICADA da API, e é a que o painel usa para mostrar a imagem —
 * inclusive a impublicável, que não tem endereço público nenhum. `src` é o valor que o editor
 * grava no conteúdo da página (a forma canônica, ver App\Support\Media\MediaUrl), e só existe
 * quando a imagem pode ir para o site.
 *
 * `usages` só vem quando o controller o carrega (detalhe), não na listagem: é uma busca por
 * página, e a grade de miniaturas não precisa dela.
 *
 * @mixin Media
 */
final class MediaResource extends JsonResource
{
    /** @var list<array{uuid: string, title: string, slug: string, status: string, places: list<string>}>|null */
    private ?array $usageList = null;

    /**
     * @param  list<array{uuid: string, title: string, slug: string, status: string, places: list<string>}>  $usages
     */
    public function withUsages(array $usages): self
    {
        $this->usageList = $usages;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $previewWidth = MediaVariants::pick($this->widths, 640);

        return [
            'id' => $this->uuid,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'depicts_assisted_minor' => $this->depicts_assisted_minor,
            'publishable' => $this->isPublishable(),
            'src' => $this->isPublishable() ? MediaUrl::canonical($this->uuid) : null,
            'preview_url' => route('media.file', ['media' => $this->uuid, 'variant' => $previewWidth.'.webp']).'?v='.$this->version,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'widths' => $this->widths,
            'version' => $this->version,
            'usages' => $this->when($this->usageList !== null, fn (): ?array => $this->usageList),
            // Só para o painel esconder o botão que não vai funcionar — quem barra é a Policy,
            // na rota (CLAUDE.md, regra 1).
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
