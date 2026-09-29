<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Audit\ListFormSubmissionAuditEntries;
use App\Actions\Audit\ListMediaAuditEntries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\IndexFormAuditRequest;
use App\Http\Requests\Audit\IndexMediaAuditRequest;
use App\Http\Resources\FormAuditEntryResource;
use App\Http\Resources\MediaAuditEntryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * Leitura do log de auditoria: dos formulários recebidos e, na aba "Imagens", da biblioteca de
 * mídia. Dos formulários — a tela "Auditoria" do painel e a seção
 * "Histórico de acessos" do detalhe de cada registro (ver docs/estrutura-site.md §4.2 e §4.5).
 *
 * Só `index`. Não existe escrita nem exclusão aqui, e nunca deve existir: um log que o painel
 * possa alterar não serve de log (ver App\Policies\ActivityPolicy).
 */
final class FormAuditController extends Controller
{
    public function index(IndexFormAuditRequest $request, ListFormSubmissionAuditEntries $action): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Activity::class);

        return FormAuditEntryResource::collection($action->handle($request->filters()));
    }

    /** A aba "Imagens": os acontecimentos da biblioteca de mídia (`log_name = media`). */
    public function media(IndexMediaAuditRequest $request, ListMediaAuditEntries $action): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Activity::class);

        return MediaAuditEntryResource::collection($action->handle($request->filters()));
    }
}
