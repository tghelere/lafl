<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FieldEncrypted;
use App\Enums\ChildAgeRange;
use App\Enums\DesiredPeriod;
use App\Models\Concerns\IsFormSubmission;
use Database\Factories\EnrollmentInterestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Manifestação de interesse na matrícula do CEI Anália Franco. Titular é o responsável adulto —
 * nenhum dado identificável da criança (ver ADR 0007). Retenção: 12 meses após contato (ver
 * docs/estrutura-site.md §2.2).
 *
 * `@property` dos dois campos abaixo por causa da mesma limitação do Larastan registrada em
 * App\Models\Concerns\IsFormSubmission: o tipo inferido de `casts()` não propaga para código
 * externo ao model (ex.: os Resources administrativos, via `@mixin`).
 *
 * @property ChildAgeRange $child_age_range
 * @property DesiredPeriod $desired_period
 *
 * @use HasFactory<EnrollmentInterestFactory>
 */
#[Fillable(['guardian_name', 'phone', 'email', 'child_age_range', 'desired_period', 'message'])]
class EnrollmentInterest extends Model
{
    /** @use HasFactory<EnrollmentInterestFactory> */
    use HasFactory, IsFormSubmission, LogsActivity {
        IsFormSubmission::getActivitylogOptions insteadof LogsActivity;
    }

    public static function retentionMonths(): int
    {
        return 12;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->commonFormCasts(),
            'guardian_name' => FieldEncrypted::class,
            'phone' => FieldEncrypted::class,
            'email' => FieldEncrypted::class,
            'child_age_range' => ChildAgeRange::class,
            'desired_period' => DesiredPeriod::class,
            'message' => FieldEncrypted::class,
        ];
    }
}
