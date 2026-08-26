<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FieldEncrypted;
use App\Models\Concerns\IsFormSubmission;
use Database\Factories\VolunteerApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Candidatura de voluntariado. Titular é o próprio voluntário adulto. Retenção: 24 meses (ver
 * docs/estrutura-site.md §2.2).
 *
 * @use HasFactory<VolunteerApplicationFactory>
 */
#[Fillable(['name', 'phone', 'email', 'availability', 'interest_area', 'message'])]
class VolunteerApplication extends Model
{
    /** @use HasFactory<VolunteerApplicationFactory> */
    use HasFactory, IsFormSubmission, LogsActivity {
        IsFormSubmission::getActivitylogOptions insteadof LogsActivity;
    }

    public static function retentionMonths(): int
    {
        return 24;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->commonFormCasts(),
            'name' => FieldEncrypted::class,
            'phone' => FieldEncrypted::class,
            'email' => FieldEncrypted::class,
            'message' => FieldEncrypted::class,
        ];
    }
}
