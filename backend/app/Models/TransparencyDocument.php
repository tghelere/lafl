<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransparencyDocumentType;
use Database\Factories\TransparencyDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @use HasFactory<TransparencyDocumentFactory>
 */
// `slug` é atribuível porque seeder e factory criam o registro direto (a Action atribui campo
// a campo) — nenhum FormRequest o aceita, e nenhuma rota o recebe: quem o gera é sempre
// App\Support\Transparency\DocumentSlug, na criação.
#[Fillable(['title', 'slug', 'year', 'type', 'file_path', 'file_size', 'published_at'])]
class TransparencyDocument extends Model
{
    /** @use HasFactory<TransparencyDocumentFactory> */
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
            'year' => 'integer',
            'type' => TransparencyDocumentType::class,
            'file_size' => 'integer',
            'download_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @param  Builder<TransparencyDocument>  $query
     * @return Builder<TransparencyDocument>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Filtro por ano/tipo compartilhado entre a listagem pública (só publicados) e a
     * administrativa (todos) — App\Actions\Transparency\ListPublicTransparencyDocuments e
     * App\Http\Controllers\Api\V1\TransparencyDocumentController::index. `null` em qualquer
     * um dos dois não filtra por aquele campo.
     *
     * @param  Builder<TransparencyDocument>  $query
     * @return Builder<TransparencyDocument>
     */
    public function scopeFilterByYearAndType(Builder $query, ?int $year, ?TransparencyDocumentType $type): Builder
    {
        return $query
            ->when($year !== null, fn (Builder $q): Builder => $q->where('year', $year))
            ->when($type !== null, fn (Builder $q): Builder => $q->where('type', $type));
    }

    /**
     * Nenhum campo pessoal aqui — documento institucional, log com valor é aceitável (ver
     * docs/protecao-de-dados.md, seção Auditoria, que restringe valor descriptografado, não
     * conteúdo público).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'year', 'type', 'published_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
