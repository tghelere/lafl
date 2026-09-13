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
 * Aviso de interesse na Escola de Contraturno. Titular é o responsável adulto — o programa
 * ainda não abriu inscrições (ver docs/contexto.md), então o único propósito do registro é
 * avisar quando abrirem; nenhum dado da criança ou adolescente é coletado (ver ADR 0007).
 * Retenção: 12 meses (ver docs/estrutura-site.md §2.2).
 *
 * @use HasFactory<ProgramApplicationFactory>
 */
#[Fillable(['guardian_name', 'phone'])]
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
        ];
    }
}
