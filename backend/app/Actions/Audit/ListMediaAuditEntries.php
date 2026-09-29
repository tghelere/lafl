<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Actions\Audit\Data\MediaAuditFilters;
use App\Enums\MediaAuditEvent;
use App\Support\InstitutionalTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

/**
 * As entradas de `activity_log` da biblioteca de mídia (`log_name = media`) — a aba "Imagens"
 * da tela de Auditoria. Separada da de formulários pelo mesmo recorte de
 * ListFormSubmissionAuditEntries: cada aba responde a uma pergunta.
 *
 * Só leitura, e só `super_admin` (App\Policies\ActivityPolicy, no controller).
 */
final class ListMediaAuditEntries
{
    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(MediaAuditFilters $filters): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->where('log_name', 'media')
            ->latest('id');

        if ($filters->causer !== null) {
            $query->where('causer_type', $filters->causer->getMorphClass())
                ->where('causer_id', $filters->causer->getKey());
        }

        if ($filters->event !== null) {
            // A marcação antiga foi gravada como `updated`; o filtro por marcação a inclui.
            $filters->event === MediaAuditEvent::Marked
                ? $query->where(fn ($inner) => $inner->where('event', 'marked')->orWhere(fn ($legacy) => $legacy
                    ->where('event', 'updated')
                    ->where('properties->attributes->depicts_assisted_minor', true)))
                : $query->where('event', $filters->event->value);
        }

        if ($filters->from !== null) {
            $query->where('created_at', '>=', InstitutionalTime::startOfDay($filters->from));
        }

        if ($filters->to !== null) {
            $query->where('created_at', '<=', InstitutionalTime::endOfDay($filters->to));
        }

        $page = $query->paginate($filters->perPage);
        $this->nameDeletedImages($page);

        return $page;
    }

    /**
     * A linha de uma imagem já excluída perde o sujeito, e a maioria dos eventos não grava o
     * texto alternativo. O `deleted` grava (DeleteMedia registra antes de apagar), e é dele que
     * as outras linhas da mesma imagem tiram o nome, para o histórico continuar legível. Fica
     * em `deleted_alt`, atributo só de leitura desta listagem.
     *
     * @param  LengthAwarePaginator<int, Activity>  $page
     */
    private function nameDeletedImages(LengthAwarePaginator $page): void
    {
        $orphans = collect($page->items())->filter(fn (Activity $entry): bool => $entry->subject === null && $entry->subject_id !== null);

        if ($orphans->isEmpty()) {
            return;
        }

        $names = Activity::query()
            ->where('log_name', 'media')
            ->where('event', 'deleted')
            ->whereIn('subject_id', $orphans->pluck('subject_id')->unique()->all())
            ->get(['subject_id', 'properties'])
            ->mapWithKeys(fn (Activity $deleted): array => [(int) $deleted->subject_id => $deleted->properties?->get('alt')]);

        foreach ($orphans as $entry) {
            $entry->setAttribute('deleted_alt', $names->get($entry->subject_id));
        }
    }
}
