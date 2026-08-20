<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<PageFactory>
 */
#[Fillable(['slug', 'title', 'content', 'meta_title', 'meta_description', 'status', 'published_at'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Rotas e payloads expõem apenas o uuid — nunca o id sequencial (ver CLAUDE.md).
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published)->whereNotNull('published_at');
    }

    /**
     * @return HasMany<PageSlugHistory, $this>
     */
    public function slugHistory(): HasMany
    {
        return $this->hasMany(PageSlugHistory::class);
    }

    /**
     * Nenhum campo pessoal aqui — conteúdo institucional, log com valor é aceitável (ver
     * docs/protecao-de-dados.md, seção Auditoria, que restringe valor descriptografado, não
     * conteúdo público).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'title', 'status', 'published_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
