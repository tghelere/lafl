<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forms\MarkSubmissionAsRead;
use App\Actions\Forms\MarkSubmissionAsUnread;
use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\ListsFormSubmissions;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\IndexFormSubmissionsRequest;
use App\Http\Requests\Forms\MarkFormSubmissionUnreadRequest;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\ProgramApplicationListResource;
use App\Http\Resources\ProgramApplicationResource;
use App\Models\ProgramApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ProgramApplicationController extends Controller
{
    use ListsFormSubmissions, LogsSubmissionAccess;

    public function index(IndexFormSubmissionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProgramApplication::class);

        $query = ProgramApplication::query()->with(['handledBy', 'readBy'])->latest('created_at');

        return ProgramApplicationListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
    }

    public function show(Request $request, ProgramApplication $programApplication, MarkSubmissionAsRead $markAsRead): ProgramApplicationResource
    {
        Gate::authorize('view', $programApplication);

        $this->logAccess($programApplication, $request);

        // Abrir o detalhe É a leitura — não existe botão "marcar como lido" (ver
        // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). Depois do log de
        // acesso, para que o registro do acesso não dependa de a escrita dar certo.
        $markAsRead->handle($programApplication, $request->user());

        $programApplication->loadMissing(['handledBy', 'readBy']);

        return new ProgramApplicationResource($programApplication);
    }

    /**
     * Devolve o registro ao estado "não lido" para a equipe inteira. `DELETE` sobre
     * `/read` — a leitura é o recurso, e o que se está fazendo é apagá-la.
     */
    public function markUnread(MarkFormSubmissionUnreadRequest $request, ProgramApplication $programApplication, MarkSubmissionAsUnread $markAsUnread): ProgramApplicationResource
    {
        $markAsUnread->handle($programApplication, $request->user(), $request->ip());

        return new ProgramApplicationResource($programApplication->fresh(['handledBy', 'readBy']));
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, ProgramApplication $programApplication): ProgramApplicationResource
    {
        $programApplication->status = FormSubmissionStatus::from($request->string('status')->value());
        $programApplication->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $programApplication->handled_by = $request->user()?->id;
        $programApplication->handled_at = now();
        $programApplication->save();

        return new ProgramApplicationResource($programApplication->fresh(['handledBy', 'readBy']));
    }
}
