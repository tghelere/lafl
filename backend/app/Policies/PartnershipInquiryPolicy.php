<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;

/**
 * `contraturno`, não `atendimento`: o único formulário de proposta de parceria do site
 * (`frontend-site/app/pages/contraturno/apoiar.vue`) é a página "Apoiar o Projeto" dentro do
 * Contraturno — não existe formulário de parceria geral (`/como-ajudar/parceiros` é conteúdo
 * institucional sobre parceiros já existentes, sem formulário).
 */
final class PartnershipInquiryPolicy extends FormSubmissionPolicy
{
    protected function allowedRoles(): array
    {
        return [...parent::allowedRoles(), Role::Contraturno->value];
    }
}
