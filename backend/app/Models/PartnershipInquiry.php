<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FieldEncrypted;
use App\Enums\PartnershipSupportType;
use App\Models\Concerns\HasBlindIndex;
use App\Models\Concerns\IsFormSubmission;
use App\Models\Contracts\FormSubmission;
use Database\Factories\PartnershipInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Proposta de parceria empresarial. Titular é o contato PJ (adulto) — sem envolvimento de
 * criança nesta ponta, por isso `message` não é cifrado (diferente de enrollment/program, ver
 * docs/dominio.md). CNPJ com blind index para checagem de duplicidade. Retenção: 36 meses
 * (ver docs/estrutura-site.md §2.2).
 *
 * `@property` abaixo pela mesma limitação do Larastan registrada em
 * App\Models\Concerns\IsFormSubmission (não propaga tipo de enum cast para `@mixin` externo).
 *
 * @property PartnershipSupportType $support_type
 *
 * @use HasFactory<PartnershipInquiryFactory>
 */
#[Fillable(['company_name', 'tax_id', 'contact_name', 'phone', 'email', 'support_type', 'message'])]
class PartnershipInquiry extends Model implements FormSubmission
{
    /** @use HasFactory<PartnershipInquiryFactory> */
    use HasBlindIndex, HasFactory, IsFormSubmission, LogsActivity {
        IsFormSubmission::getActivitylogOptions insteadof LogsActivity;
    }

    /**
     * @var array<string, string>
     */
    protected array $blindIndexes = [
        'tax_id' => 'tax_id_hash',
    ];

    public static function retentionMonths(): int
    {
        return 36;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->commonFormCasts(),
            'tax_id' => FieldEncrypted::class,
            'contact_name' => FieldEncrypted::class,
            'phone' => FieldEncrypted::class,
            'email' => FieldEncrypted::class,
            'support_type' => PartnershipSupportType::class,
        ];
    }
}
