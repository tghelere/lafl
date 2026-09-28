<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\ContactMessage;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\VolunteerApplication;
use Illuminate\Database\Eloquent\Model;

/**
 * Um caso por entidade de formulário recebido (ver docs/dominio.md). Centraliza o que varia
 * só por tipo — rótulo em português, slug de `/obrigado/:tipo` (ver docs/estrutura-site.md
 * §1.3), model correspondente e nome do recurso no painel administrativo — para as cinco
 * Actions de criação, o e-mail de notificação e o painel de pendências (Etapa 1 da sessão 6)
 * não repetirem essa tabela cada um a seu modo.
 */
enum FormSubmissionType: string
{
    case ProgramApplication = 'program_application';
    case PickupRequest = 'pickup_request';
    case VolunteerApplication = 'volunteer_application';
    case PartnershipInquiry = 'partnership_inquiry';
    case ContactMessage = 'contact_message';

    public function label(): string
    {
        return match ($this) {
            self::ProgramApplication => 'Aviso de interesse no contraturno',
            self::PickupRequest => 'Agendamento de coleta',
            self::VolunteerApplication => 'Candidatura de voluntariado',
            self::PartnershipInquiry => 'Proposta de parceria',
            self::ContactMessage => 'Mensagem de contato',
        };
    }

    /**
     * Token usado em `/obrigado/:tipo` — ver docs/estrutura-site.md §1.3.
     */
    public function thankYouSlug(): string
    {
        return match ($this) {
            self::ProgramApplication => 'inscricao',
            self::PickupRequest => 'coleta',
            self::VolunteerApplication => 'voluntariado',
            self::PartnershipInquiry => 'parceria',
            self::ContactMessage => 'contato',
        };
    }

    /**
     * Nome do recurso no painel administrativo (`docs/estrutura-site.md` §4.5) — usado para
     * montar o link do e-mail de notificação e é o mesmo slug da rota `/admin/:resource` do
     * painel (SubmissionListView.vue / SubmissionDetailView.vue).
     */
    public function adminResourceSlug(): string
    {
        return match ($this) {
            self::ProgramApplication => 'program-applications',
            self::PickupRequest => 'pickup-requests',
            self::VolunteerApplication => 'volunteer-applications',
            self::PartnershipInquiry => 'partnership-inquiries',
            self::ContactMessage => 'contact-messages',
        };
    }

    /**
     * O caminho de volta de `modelClass()`: dado um model qualquer, qual tipo de formulário ele é
     * — `null` quando não é nenhum. Usado pela tela de Auditoria, que lê `activity_log`, onde o
     * que existe é a classe do sujeito, não o tipo (ver App\Http\Resources\FormAuditEntryResource).
     */
    public static function forModel(?Model $model): ?self
    {
        if ($model === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($model instanceof ($case->modelClass())) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return class-string<ProgramApplication|PickupRequest|VolunteerApplication|PartnershipInquiry|ContactMessage>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::ProgramApplication => ProgramApplication::class,
            self::PickupRequest => PickupRequest::class,
            self::VolunteerApplication => VolunteerApplication::class,
            self::PartnershipInquiry => PartnershipInquiry::class,
            self::ContactMessage => ContactMessage::class,
        };
    }
}
