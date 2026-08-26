<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\FormSubmissionStatus;
use App\Enums\FormSubmissionType;
use App\Models\User;

/**
 * Alimenta a tela Início do painel (ver docs/estrutura-site.md §4.2: "Painel com pendências
 * ... todos, filtrado pelo papel"). Não usa Policy::viewAny por injeção genérica de Model —
 * cada tipo já sabe sua própria classe (App\Enums\FormSubmissionType::modelClass()), então o
 * Gate simplesmente pergunta por essa classe concreta. `comunicacao` não tem `viewAny` em
 * nenhuma das seis (ver App\Policies\FormSubmissionPolicy), então o resultado para ela é uma
 * lista vazia — "recebe zero de tudo" sai da própria Policy, não de um caso especial aqui.
 */
final class GetPendingFormSubmissionCounts
{
    /**
     * @return list<array{type: string, label: string, pending: int}>
     */
    public function handle(User $user): array
    {
        $summary = [];

        foreach (FormSubmissionType::cases() as $type) {
            $modelClass = $type->modelClass();

            if (! $user->can('viewAny', $modelClass)) {
                continue;
            }

            $summary[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'pending' => $modelClass::query()->where('status', FormSubmissionStatus::New)->count(),
            ];
        }

        return $summary;
    }
}
