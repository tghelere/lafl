<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status de ATENDIMENTO, compartilhado pelas cinco entidades de formulário recebido (ver
 * docs/dominio.md, "Formulários recebidos"). Mudança de status é ato de atendimento/direção
 * via painel, pela tela de detalhe de cada recurso (SubmissionDetailView.vue +
 * InternalNoteForm.vue).
 *
 * Não existe mais o caso `new`. Ele não dizia nada sobre o atendimento — dizia "ninguém mexeu
 * nisto ainda", que é informação de LEITURA, e leitura agora tem colunas próprias (`read_at`,
 * `read_by`, ver App\Models\Concerns\IsFormSubmission). Manter os dois era manter duas
 * respostas para a mesma pergunta, que divergem na primeira vez que alguém abre um registro e
 * não muda o status. Ver docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md.
 *
 * Por isso `InProgress` é o estado inicial: o formulário nasce sob responsabilidade da
 * instituição, e é `read_at` — não o status — que responde se alguém já olhou.
 *
 * `Done` se chama "Concluído", e não "Respondido", porque serve às cinco entidades: uma
 * mensagem de contato se responde, uma coleta se realiza, uma proposta de apoio se fecha. O
 * expurgo do endereço do doador depende deste caso (ver App\Models\PickupRequest), então o
 * rótulo precisa valer para uma coleta feita.
 */
enum FormSubmissionStatus: string
{
    case InProgress = 'in_progress';
    case Done = 'done';
    case Archived = 'archived';

    /**
     * Status de um formulário recém-recebido.
     */
    public static function initial(): self
    {
        return self::InProgress;
    }

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Em atendimento',
            self::Done => 'Concluído',
            self::Archived => 'Arquivado',
        };
    }
}
