<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Casts\FieldEncrypted;
use App\Enums\FormSubmissionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Comportamento comum às seis entidades de formulário recebido (ver docs/dominio.md,
 * "Formulários recebidos"): uuid como chave de rota, status inicial, prazo de retenção
 * default e o relacionamento com quem atendeu.
 *
 * O model que usa este trait deve implementar `retentionMonths(): int`, com o prazo de
 * `docs/estrutura-site.md` §2.2. `pickup_requests` também depende de um expurgo específico do
 * endereço na conclusão da coleta — isso não mora aqui, é responsabilidade só daquele model
 * (ver App\Models\PickupRequest).
 *
 * Os `@property` abaixo existem só para o PHPStan/Larastan enxergar as colunas comuns dentro
 * desta trait e das seis Actions de criação — ele infere `$this->coluna` automaticamente
 * dentro do próprio model (via `casts()` + schema), mas não propaga isso para código externo
 * que recebe o model como parâmetro. Manter em sincronia com FormSubmissionColumns::addCommon().
 *
 * Timestamps declarados `|null`, mesmo nunca sendo null na prática num registro já
 * persistido — só para poder seguir usando `?->` como o resto do código já faz para
 * `created_at` em outros models (ver App\Http\Resources\PageResource), sem o Larastan
 * reclamar de "nullsafe desnecessário" por causa desta anotação explícita mais estrita.
 *
 * @property string $uuid
 * @property FormSubmissionStatus $status
 * @property string $consent_terms_version
 * @property Carbon|null $consented_at
 * @property string $ip_hash
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property string|null $internal_note
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
trait IsFormSubmission
{
    abstract public static function retentionMonths(): int;

    public static function bootIsFormSubmission(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
            $model->status ??= FormSubmissionStatus::New;
            $model->expires_at ??= now()->addMonths(static::retentionMonths());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function commonFormCasts(): array
    {
        return [
            'status' => FormSubmissionStatus::class,
            'consented_at' => 'datetime',
            'handled_at' => 'datetime',
            'expires_at' => 'datetime',
            'internal_note' => FieldEncrypted::class,
        ];
    }

    /**
     * Nenhum campo pessoal é logado — só o que muda por ação de atendimento (ver
     * docs/protecao-de-dados.md, "Auditoria": o log registra o acesso, nunca o valor
     * descriptografado). `internal_note` fica de fora mesmo cifrado, para não versionar seu
     * conteúdo no log a cada edição.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'handled_by', 'handled_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
