<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status compartilhado pelas seis entidades de formulário recebido (ver docs/dominio.md,
 * "Formulários recebidos"). Mudança de status é ato de atendimento/direção via painel, pela
 * tela de detalhe de cada recurso (SubmissionDetailView.vue + InternalNoteForm.vue).
 */
enum FormSubmissionStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::InProgress => 'Em andamento',
            self::Done => 'Concluído',
            self::Discarded => 'Descartado',
        };
    }
}
