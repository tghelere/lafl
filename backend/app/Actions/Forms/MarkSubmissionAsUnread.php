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
 * Devolve um formulário recebido ao estado "não lido", para a equipe inteira — é o
 * "deixo isto para depois" de quem abriu um registro e não pôde tratá-lo (ver
 * docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md).
 *
 * Tem evento próprio no log de auditoria porque é ato deliberado, e não decorre de acesso
 * nenhum: `read_at`/`read_by` estão fora do `logOnly` de App\Models\Concerns\IsFormSubmission
 * (ver o docblock de lá), então sem este `activity()` a ação não deixaria rastro. E ela apaga
 * rastro visível na tela — quem marcou como não lido esconde da equipe que o registro já havia
 * sido aberto, o que é exatamente o tipo de coisa que a auditoria existe para registrar.
 */
final class MarkSubmissionAsUnread
{
    /**
     * `Model` na assinatura porque a rota resolve uma das cinco entidades; o @param estreita
     * para o Larastan enxergar as colunas comuns da trait (ver
     * App\Models\Concerns\IsFormSubmission), do mesmo jeito que
     * App\Enums\FormSubmissionType::modelClass() já faz.
     *
     * @param  ProgramApplication|PickupRequest|VolunteerApplication|PartnershipInquiry|ContactMessage  $submission
     */
    public function handle(Model $submission, ?User $actor): void
    {
        if ($submission->read_at === null) {
            return;
        }

        $submission->read_at = null;
        $submission->read_by = null;
        $submission->save();

        activity('forms')
            ->causedBy($actor)
            ->performedOn($submission)
            ->event('marked_unread')
            ->log('Formulário marcado como não lido');
    }
}
