<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FieldEncrypted;
use App\Enums\FormSubmissionStatus;
use App\Models\Concerns\IsFormSubmission;
use App\Models\Contracts\FormSubmission;
use Database\Factories\PickupRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Agendamento de coleta do Bazar Beneficente. Titular é o doador adulto. `address` é o dado
 * mais sensível desta fase (ver docs/dominio.md) — expurgado assim que a coleta é concluída
 * (status `done`), via App\Jobs\PurgeCompletedPickupRequestAddresses, além (não em vez) do
 * expurgo geral por `expires_at` que as outras cinco entidades também têm.
 *
 * @property Carbon|null $scheduled_for
 *
 * @use HasFactory<PickupRequestFactory>
 */
#[Fillable(['donor_name', 'phone', 'address', 'items_description', 'availability_window'])]
class PickupRequest extends Model implements FormSubmission
{
    /** @use HasFactory<PickupRequestFactory> */
    use HasFactory, IsFormSubmission, LogsActivity {
        IsFormSubmission::getActivitylogOptions insteadof LogsActivity;
    }

    /**
     * Rede de segurança: mesmo que a coleta nunca seja marcada como concluída, o registro
     * inteiro (endereço incluído) desaparece em 6 meses de qualquer forma (ver
     * docs/estrutura-site.md §2.2, "6 meses após coleta" — aqui contado da criação, não da
     * coleta, por não haver garantia de que o status será atualizado).
     */
    public static function retentionMonths(): int
    {
        return 6;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->commonFormCasts(),
            'donor_name' => FieldEncrypted::class,
            'phone' => FieldEncrypted::class,
            'address' => FieldEncrypted::class,
            'scheduled_for' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PickupRequest>  $query
     * @return Builder<PickupRequest>
     */
    public function scopeCompletedWithAddress(Builder $query): Builder
    {
        return $query->where('status', FormSubmissionStatus::Done)->whereNotNull('address');
    }
}
