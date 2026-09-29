<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageImageRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma imagem da biblioteca na capa ou na galeria de uma página (ver App\Enums\PageImageRole).
 *
 * Só a ligação: arquivo, texto alternativo e legenda ficam em App\Models\Media, e a mesma
 * imagem em duas páginas é uma imagem só. Nada é atribuível em massa, porque toda escrita passa
 * pelas Actions de App\Actions\Content\PageImages.
 *
 * Os `@property` abaixo corrigem o que o Larastan infere da migration: as chaves estrangeiras
 * saem de `foreignId` (sem sinal) como `int<0, max>`, e as duas relações como anuláveis, mas
 * as colunas são `NOT NULL` com cascata dos dois lados, então a ligação sempre tem página e
 * imagem.
 *
 * @property int $page_id
 * @property int $media_id
 * @property-read Page $page
 * @property-read Media $media
 */
class PageImage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PageImageRole::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
