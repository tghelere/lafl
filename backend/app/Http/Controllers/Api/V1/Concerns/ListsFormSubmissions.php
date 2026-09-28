<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Enums\FormSubmissionStatus;
use App\Http\Requests\Forms\IndexFormSubmissionsRequest;
use App\Support\InstitutionalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtros e paginação das cinco listagens administrativas de formulário recebido (ver
 * docs/estrutura-site.md §4.5). Estavam copiados literalmente nos cinco controllers; o fuso
 * do filtro de data (ver App\Support\InstitutionalTime) e o filtro de leitura teriam sido a
 * terceira e a quarta cópia.
 *
 * O que NÃO está aqui, de propósito: a ordenação. Ela é declarada por controller, na query que
 * chega, porque é a única coisa que uma listagem pode querer diferente da outra.
 */
trait ListsFormSubmissions
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySubmissionFilters(Builder $query, IndexFormSubmissionsRequest $request): Builder
    {
        $status = $request->filled('status')
            ? FormSubmissionStatus::tryFrom($request->string('status')->value())
            : null;

        if ($status !== null) {
            $query->where('status', $status);
        }

        // Comparação contra o instante, não contra a data: `whereDate` compararia a data do
        // UTC gravado, e o dia de quem filtra é o dia em America/Sao_Paulo — três horas de
        // diferença que jogariam registros para o dia errado nas duas pontas do intervalo.
        if ($request->filled('from')) {
            $query->where('created_at', '>=', InstitutionalTime::startOfDay($request->string('from')->value()));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', InstitutionalTime::endOfDay($request->string('to')->value()));
        }

        return $query;
    }

    protected function submissionsPerPage(IndexFormSubmissionsRequest $request): int
    {
        // O teto de 100 já é regra de validação do FormRequest; o min() aqui é cinto e
        // suspensório para o caso de a regra mudar de lugar.
        return min($request->integer('per_page', 15), 100);
    }
}
