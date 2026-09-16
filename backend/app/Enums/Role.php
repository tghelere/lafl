<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Papéis de acesso ao painel administrativo — ver docs/dominio.md, seção "Papéis". São por
 * área de atuação, não por cargo — organograma muda, a área que um formulário ou conteúdo
 * pertence não. Um usuário pode acumular vários papéis (ex.: quem cuida do financeiro também
 * cobre o contraturno). A matriz de acesso por recurso vive em Policies, não neste enum:
 * papéis são agrupamentos de conveniência, não a fonte da verdade de autorização.
 *
 * Não existe papel para a creche (CEI) — a Educação Infantil não tem formulário recebido
 * próprio (matrícula aponta para a Central de Vagas da Prefeitura, ver docs/roadmap.md) nem
 * conteúdo administrado fora de `pages`, que já é `comunicacao`/`direcao`.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Direcao = 'direcao';
    case Financeiro = 'financeiro';
    case Contraturno = 'contraturno';
    case Bazar = 'bazar';
    case Atendimento = 'atendimento';
    case Comunicacao = 'comunicacao';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Direcao => 'Direção',
            self::Financeiro => 'Financeiro',
            self::Contraturno => 'Contraturno',
            self::Bazar => 'Bazar',
            self::Atendimento => 'Atendimento',
            self::Comunicacao => 'Comunicação',
        };
    }
}
