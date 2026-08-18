<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papéis de acesso ao painel administrativo — ver docs/dominio.md, seção "Papéis e
 * permissões". A matriz de acesso por recurso vive em Policies, não neste enum: papéis são
 * agrupamentos de conveniência, não a fonte da verdade de autorização.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Coordination = 'coordination';
    case SocialWork = 'social_work';
    case Psychology = 'psychology';
    case Pedagogy = 'pedagogy';
    case Administrative = 'administrative';
    case ContentEditor = 'content_editor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Coordination => 'Coordenação',
            self::SocialWork => 'Serviço social',
            self::Psychology => 'Psicologia',
            self::Pedagogy => 'Pedagogia',
            self::Administrative => 'Administrativo',
            self::ContentEditor => 'Editor de conteúdo',
        };
    }
}
