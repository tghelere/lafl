<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Período pretendido pelo responsável na manifestação de interesse. O CEI Anália Franco hoje
 * opera só em período integral (7h30–17h30, ver docs/contexto.md) — as opções abaixo
 * capturam a preferência da família, não uma disponibilidade confirmada da instituição; quem
 * atende decide a viabilidade manualmente. Não inventar uma oferta de meio período que a
 * instituição não confirmou (ver docs/roadmap.md, decisão de produto registrada).
 */
enum DesiredPeriod: string
{
    case Manha = 'manha';
    case Tarde = 'tarde';
    case Integral = 'integral';

    public function label(): string
    {
        return match ($this) {
            self::Manha => 'Manhã',
            self::Tarde => 'Tarde',
            self::Integral => 'Integral',
        };
    }
}
