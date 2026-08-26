<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FieldEncrypted;
use App\Models\Concerns\IsFormSubmission;
use Database\Factories\ProgramApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Inscrição na Escola de Contraturno. Titular é o responsável adulto — nenhum dado
 * identificável do adolescente (ver ADR 0007). `teen_age` é número inteiro, nunca data de
 * nascimento. Retenção: 12 meses (ver docs/estrutura-site.md §2.2).
 *
 * @use HasFactory<ProgramApplicationFactory>
 */
#[Fillable(['guardian_name', 'phone', 'email', 'teen_age', 'school', 'message'])]
class ProgramApplication extends Model
{
    /** @use HasFactory<ProgramApplicationFactory> */
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
            'teen_age' => 'integer',
            'school' => FieldEncrypted::class,
            'message' => FieldEncrypted::class,
        ];
    }
}
