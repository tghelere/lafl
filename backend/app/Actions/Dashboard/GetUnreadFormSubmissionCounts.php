<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enums\FormSubmissionType;
use App\Models\User;

/**
 * Alimenta os cards da tela Início e os contadores da navegação lateral do painel (ver
 * docs/estrutura-site.md §4.2: "Painel com pendências ... todos, filtrado pelo papel").
 *
 * Conta NÃO LIDOS, não mais registros com status `new`. O status `new` deixou de existir
 * justamente porque era este contador disfarçado: "ninguém mexeu nisto ainda" é leitura, não
 * atendimento (ver docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). A
 * diferença prática é que agora o contador cai quando alguém abre o registro, sem exigir que a
 * pessoa também escolha um status — e volta a subir se ela marcar como não lido.
 *
 * Não usa Policy::viewAny por injeção genérica de Model — cada tipo já sabe sua própria classe
 * (App\Enums\FormSubmissionType::modelClass()), então o Gate simplesmente pergunta por essa
 * classe concreta. `comunicacao` não tem `viewAny` em nenhuma das cinco (ver
 * App\Policies\FormSubmissionPolicy), então o resultado para ela é uma lista vazia — "recebe
 * zero de tudo" sai da própria Policy, não de um caso especial aqui.
 */
final class GetUnreadFormSubmissionCounts
{
    /**
     * @return list<array{type: string, label: string, resource: string, unread: int}>
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
                // O slug da rota do painel vem da API, e não de um mapa reverso mantido à mão
                // no frontend (era o que DashboardView.vue fazia) — a correspondência entre
                // tipo e recurso é a mesma que o resto da API já usa.
                'resource' => $type->adminResourceSlug(),
                'unread' => $modelClass::query()->unread()->count(),
            ];
        }

        return $summary;
    }
}
