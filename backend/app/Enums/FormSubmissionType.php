<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Um caso por entidade de formulário recebido (ver docs/dominio.md). Centraliza o que varia
 * só por tipo — rótulo em português, slug de `/obrigado/:tipo` (ver docs/estrutura-site.md
 * §1.3) e nome do recurso no painel administrativo — para as seis Actions de criação e o
 * e-mail de notificação não repetirem essa tabela cada uma a seu modo.
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
            self::ProgramApplication => 'Inscrição no contraturno',
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
}
