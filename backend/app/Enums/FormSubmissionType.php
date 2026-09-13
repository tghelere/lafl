<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\ContactMessage;
use App\Models\EnrollmentInterest;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\VolunteerApplication;

/**
 * Um caso por entidade de formulário recebido (ver docs/dominio.md). Centraliza o que varia
 * só por tipo — rótulo em português, slug de `/obrigado/:tipo` (ver docs/estrutura-site.md
 * §1.3), model correspondente e nome do recurso no painel administrativo — para as seis
 * Actions de criação, o e-mail de notificação e o painel de pendências (Etapa 1 da sessão 6)
 * não repetirem essa tabela cada um a seu modo.
 */
enum FormSubmissionType: string
{
    case EnrollmentInterest = 'enrollment_interest';
    case ProgramApplication = 'program_application';
    case PickupRequest = 'pickup_request';
    case VolunteerApplication = 'volunteer_application';
    case PartnershipInquiry = 'partnership_inquiry';
    case ContactMessage = 'contact_message';

    public function label(): string
    {
        return match ($this) {
            self::EnrollmentInterest => 'Interesse em matrícula',
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
            self::EnrollmentInterest => 'matricula',
            self::ProgramApplication => 'inscricao',
            self::PickupRequest => 'coleta',
            self::VolunteerApplication => 'voluntariado',
            self::PartnershipInquiry => 'parceria',
            self::ContactMessage => 'contato',
        };
    }

    /**
     * Nome do recurso no painel administrativo (`docs/estrutura-site.md` §4.5) — usado só para
     * montar o link do e-mail de notificação; a tela em si ainda não existe (ver
     * docs/roadmap.md).
     */
    public function adminResourceSlug(): string
    {
        return match ($this) {
            self::EnrollmentInterest => 'enrollment-interests',
            self::ProgramApplication => 'program-applications',
            self::PickupRequest => 'pickup-requests',
            self::VolunteerApplication => 'volunteer-applications',
            self::PartnershipInquiry => 'partnership-inquiries',
            self::ContactMessage => 'contact-messages',
        };
    }

    /**
     * @return class-string<EnrollmentInterest|ProgramApplication|PickupRequest|VolunteerApplication|PartnershipInquiry|ContactMessage>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::EnrollmentInterest => EnrollmentInterest::class,
            self::ProgramApplication => ProgramApplication::class,
            self::PickupRequest => PickupRequest::class,
            self::VolunteerApplication => VolunteerApplication::class,
            self::PartnershipInquiry => PartnershipInquiry::class,
            self::ContactMessage => ContactMessage::class,
        };
    }
}
