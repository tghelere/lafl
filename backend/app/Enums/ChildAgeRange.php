<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Faixa etária da criança, nunca data de nascimento — regra não negociável desta fase (ver
 * ADR 0007 e docs/estrutura-site.md §2.1: o titular do formulário é o responsável, e nenhum
 * dado identificável de menor entra pela web). O CEI Tio Pedro atende de 1 a 5 anos, turmas
 * C1 a P5 (ver docs/contexto.md).
 */
enum ChildAgeRange: string
{
    case UmAno = '1_ano';
    case DoisAnos = '2_anos';
    case TresAnos = '3_anos';
    case QuatroAnos = '4_anos';
    case CincoAnos = '5_anos';

    public function label(): string
    {
        return match ($this) {
            self::UmAno => '1 ano',
            self::DoisAnos => '2 anos',
            self::TresAnos => '3 anos',
            self::QuatroAnos => '4 anos',
            self::CincoAnos => '5 anos',
        };
    }
}
