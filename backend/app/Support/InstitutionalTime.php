<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Tradução entre o UTC em que todo timestamp é gravado (ver config/app.php) e o fuso de quem
 * lê e filtra — `America/Sao_Paulo`, declarado em config/institution.php.
 *
 * Não é preciosismo: o servidor fica nos Estados Unidos (ver docs/protecao-de-dados.md,
 * "Transferência internacional"), então o "hoje" do servidor não é o "hoje" de quem usa o
 * painel. Das 21h à meia-noite de Londrina o UTC já está no dia seguinte — um formulário
 * recebido às 22h de segunda apareceria como terça, e o filtro "De 2026-09-28" deixaria de
 * fora as três primeiras horas daquele dia local.
 *
 * Fica num lugar só, e não espalhado por Resource, Controller e e-mail de notificação, para
 * que o formato e o fuso sejam decididos uma única vez. O painel recebe a string já pronta —
 * regra 1 do CLAUDE.md: a API é a única fonte de verdade, inclusive do texto que a tela
 * mostra.
 */
final class InstitutionalTime
{
    /**
     * Formato de exibição de data e hora no painel — dd/mm/aaaa HH:mm.
     */
    public const LABEL_FORMAT = 'd/m/Y H:i';

    public static function timezone(): string
    {
        return (string) config('institution.timezone');
    }

    /**
     * `null` entra, `null` sai: timestamp opcional (`read_at`, `handled_at`) não precisa de um
     * ramo próprio em cada Resource.
     */
    public static function label(?DateTimeInterface $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        return CarbonImmutable::instance($moment)
            ->setTimezone(self::timezone())
            ->format(self::LABEL_FORMAT);
    }

    /**
     * Primeiro instante, em UTC, do dia `Y-m-d` informado no fuso institucional —
     * `2026-09-28` vira `2026-09-28T03:00:00Z`. É contra isto que o filtro "De" compara a
     * coluna, e não com `whereDate`, que compararia a data do UTC.
     */
    public static function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->startOfDay()->utc();
    }

    /**
     * Último instante, em UTC, do dia `Y-m-d` informado no fuso institucional —
     * `2026-09-28` vira `2026-09-29T02:59:59Z`. Ver startOfDay().
     */
    public static function endOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->endOfDay()->utc();
    }
}
