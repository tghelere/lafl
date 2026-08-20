<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['page_id', 'slug'])]
class PageSlugHistory extends Model
{
    /**
     * Nome exato definido em docs/dominio.md — o pluralizador padrão do Eloquent
     * transformaria "history" em "histories", divergindo da migration.
     */
    protected $table = 'page_slug_history';

    /**
     * Só created_at existe nesta tabela (ver docs/dominio.md) — nunca é atualizada, só
     * criada.
     */
    const UPDATED_AT = null;

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
