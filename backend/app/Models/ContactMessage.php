<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FieldEncrypted;
use App\Models\Concerns\IsFormSubmission;
use App\Models\Contracts\FormSubmission;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Mensagem de contato geral. Titular é o próprio visitante adulto. Retenção: 6 meses (ver
 * docs/estrutura-site.md §2.2).
 *
 * @use HasFactory<ContactMessageFactory>
 */
#[Fillable(['name', 'email', 'subject', 'message'])]
class ContactMessage extends Model implements FormSubmission
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory, IsFormSubmission, LogsActivity {
        IsFormSubmission::getActivitylogOptions insteadof LogsActivity;
    }

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
            'name' => FieldEncrypted::class,
            'email' => FieldEncrypted::class,
            'message' => FieldEncrypted::class,
        ];
    }
}
