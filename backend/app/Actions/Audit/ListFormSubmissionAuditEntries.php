<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Actions\Audit\Data\FormAuditFilters;
use App\Enums\FormSubmissionType;
use App\Support\InstitutionalTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * As entradas de `activity_log` cujo sujeito é um formulário recebido — é o que alimenta a tela
 * de Auditoria e a seção "Histórico de acessos" do detalhe.
 *
 * Recorte deliberado: só formulário. A mesma tabela guarda também acesso, login e alteração de
 * conta (logs `users` e `auth`, ver App\Listeners\Auth\*), e misturar tudo daria uma tela que não
 * responde a pergunta nenhuma. Auditoria de conta é outra tela, quando existir.
 *
 * Só leitura, e só `super_admin` — a autorização é do controller, via Policy (ver
 * App\Policies\ActivityPolicy).
 */
final class ListFormSubmissionAuditEntries
{
    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(FormAuditFilters $filters): LengthAwarePaginator
    {
        // `subject` junto: o Resource precisa do uuid do registro para montar o link, e
        // `Model::preventLazyLoading()` está ativo em desenvolvimento (ver AppServiceProvider) —
        // sem o eager load isto estouraria na primeira linha da tela.
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->whereIn('subject_type', $this->subjectTypes($filters->type))
            ->latest('id');

        if ($filters->causer !== null) {
            $query->where('causer_type', $filters->causer->getMorphClass())
                ->where('causer_id', $filters->causer->getKey());
        }

        // Mesmo fuso do resto do painel: o dia do filtro é o dia em Londrina, não o do UTC
        // gravado (ver App\Support\InstitutionalTime).
        if ($filters->from !== null) {
            $query->where('created_at', '>=', InstitutionalTime::startOfDay($filters->from));
        }

        if ($filters->to !== null) {
            $query->where('created_at', '<=', InstitutionalTime::endOfDay($filters->to));
        }

        $this->applyRecordFilter($query, $filters->record);

        return $query->paginate($filters->perPage);
    }

    /**
     * Um uuid não identifica linha de `activity_log` — ela guarda `subject_id`, que é a chave
     * sequencial. Resolver o uuid para a chave aqui é o que mantém a promessa de que uuid é o
     * único identificador que entra por rota ou payload (CLAUDE.md, regra 4).
     *
     * Uuid que não existe em nenhuma das cinco tabelas devolve lista vazia, nunca a tabela
     * inteira — daí o `whereRaw('1 = 0')` em vez de simplesmente não filtrar.
     *
     * @param  Builder<Activity>  $query
     */
    private function applyRecordFilter(Builder $query, ?string $record): void
    {
        if ($record === null) {
            return;
        }

        foreach (FormSubmissionType::cases() as $type) {
            $submission = $type->modelClass()::query()->where('uuid', $record)->first();

            if ($submission !== null) {
                $query->where('subject_type', $submission->getMorphClass())
                    ->where('subject_id', $submission->getKey());

                return;
            }
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @return list<string>
     */
    private function subjectTypes(?FormSubmissionType $type): array
    {
        $types = $type !== null ? [$type] : FormSubmissionType::cases();

        return array_map(
            fn (FormSubmissionType $case): string => (new ($case->modelClass())())->getMorphClass(),
            $types,
        );
    }
}
