<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papéis de acesso ao painel administrativo — ver docs/estrutura-site.md, seção 4.4. Os
 * papéis são modelados pelo tipo de dado que tocam (conteúdo público, formulário recebido,
 * cadastro de assistido), não por cargo — organograma muda, a classificação do dado não. A
 * matriz de acesso por recurso vive em Policies, não neste enum: papéis são agrupamentos de
 * conveniência, não a fonte da verdade de autorização.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Direcao = 'direcao';
    case Atendimento = 'atendimento';
    case Bazar = 'bazar';
    case Comunicacao = 'comunicacao';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Direcao => 'Direção',
            self::Atendimento => 'Atendimento',
            self::Bazar => 'Bazar',
            self::Comunicacao => 'Comunicação',
        };
    }
}
