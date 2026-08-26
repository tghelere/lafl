<?php

declare(strict_types=1);

namespace App\Enums;

enum PartnershipSupportType: string
{
    case Financial = 'financial';
    case InKind = 'in_kind';
    case Volunteering = 'volunteering';
    case Sponsorship = 'sponsorship';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Financial => 'Apoio financeiro',
            self::InKind => 'Doação de materiais ou serviços',
            self::Volunteering => 'Voluntariado corporativo',
            self::Sponsorship => 'Patrocínio de evento',
            self::Other => 'Outro',
        };
    }
}
