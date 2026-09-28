<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Os acontecimentos que a tela de Auditoria mostra para um formulário recebido. Um caso por
 * `event` que o projeto grava em `activity_log` sobre uma das cinco entidades:
 *
 * | Caso | Quem grava |
 * |---|---|
 * | `created`, `updated` | `LogsActivity` do model (ver App\Models\Concerns\IsFormSubmission) |
 * | `viewed` | App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess, em todo `show()` |
 * | `marked_unread` | App\Actions\Forms\MarkSubmissionAsUnread |
 *
 * Existe para que os rótulos em português da tela não sejam string mágica espalhada (ver
 * CLAUDE.md, "Convenções essenciais"). `event` vem do banco como texto livre, então a leitura é
 * sempre por `tryFrom` — um valor desconhecido aparece cru na tela em vez de virar exceção, que
 * é o comportamento certo para um log histórico.
 */
enum FormAuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Viewed = 'viewed';
    case MarkedUnread = 'marked_unread';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Recebido pelo site',
            self::Updated => 'Atendimento alterado',
            self::Viewed => 'Detalhe acessado',
            self::MarkedUnread => 'Marcado como não lido',
            self::Deleted => 'Registro excluído',
        };
    }

    public static function labelFor(?string $event): string
    {
        return self::tryFrom((string) $event)?->label() ?? (string) $event;
    }
}
