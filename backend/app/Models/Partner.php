<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Empresa ou instituição parceira, mostrada com a logo na página de parceiros do site.
 *
 * @property int $media_id
 * @property-read Media $media
 */
#[Fillable(['name', 'url', 'position', 'is_active'])]
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

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
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $partner): void {
            $partner->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Ativos, na ordem do painel; empate pela criação, para a ordem ser determinística.
     *
     * @param  Builder<Partner>  $query
     * @return Builder<Partner>
     */
    public function scopeActiveOrdered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position')->orderBy('id');
    }

    /**
     * Nenhum dado pessoal: nome e site de empresa parceira, conteúdo público.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'url', 'position', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
