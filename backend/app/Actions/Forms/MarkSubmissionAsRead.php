<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Models\ContactMessage;
use App\Models\PartnershipInquiry;
use App\Models\PickupRequest;
use App\Models\ProgramApplication;
use App\Models\User;
use App\Models\VolunteerApplication;
use Illuminate\Database\Eloquent\Model;

/**
 * Marca um formulário recebido como lido pela equipe. Chamada por todo `show()` administrativo:
 * abrir o detalhe É a leitura, não existe botão "marcar como lido" (ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md).
 *
 * Só a PRIMEIRA abertura conta. Quem já está lido continua com o `read_at` e o `read_by` da
 * primeira vez — senão a coluna passaria a significar "último acesso", que é outra coisa e já
 * está no log de auditoria. É também o que faz o botão "Marcar como não lido" ter efeito
 * observável: sem esta guarda, a próxima pessoa a abrir o registro sobrescreveria o histórico.
 */
final class MarkSubmissionAsRead
{
    /**
     * `Model` na assinatura porque a rota resolve uma das cinco entidades; o @param estreita
     * para o Larastan enxergar as colunas comuns da trait (ver
     * App\Models\Concerns\IsFormSubmission), do mesmo jeito que
     * App\Enums\FormSubmissionType::modelClass() já faz.
     *
     * @param  ProgramApplication|PickupRequest|VolunteerApplication|PartnershipInquiry|ContactMessage  $submission
     */
    public function handle(Model $submission, ?User $reader): void
    {
        if ($submission->read_at !== null) {
            return;
        }

        $submission->read_at = now();
        $submission->read_by = $reader?->id;
        $submission->save();
    }
}
