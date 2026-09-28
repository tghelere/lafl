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
use App\Http\Resources\VolunteerApplicationListResource;
use App\Http\Resources\VolunteerApplicationResource;
use App\Models\VolunteerApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class VolunteerApplicationController extends Controller
{
    use ListsFormSubmissions, LogsSubmissionAccess;

    public function index(IndexFormSubmissionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', VolunteerApplication::class);

        $query = VolunteerApplication::query()->with(['handledBy', 'readBy'])->latest('created_at');

        return VolunteerApplicationListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
    }

    public function show(Request $request, VolunteerApplication $volunteerApplication, MarkSubmissionAsRead $markAsRead): VolunteerApplicationResource
    {
        Gate::authorize('view', $volunteerApplication);

        $this->logAccess($volunteerApplication, $request);

        // Abrir o detalhe É a leitura — não existe botão "marcar como lido" (ver
        // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). Depois do log de
        // acesso, para que o registro do acesso não dependa de a escrita dar certo.
        $markAsRead->handle($volunteerApplication, $request->user());

        $volunteerApplication->loadMissing(['handledBy', 'readBy']);

        return new VolunteerApplicationResource($volunteerApplication);
    }

    /**
     * Devolve o registro ao estado "não lido" para a equipe inteira. `DELETE` sobre
     * `/read` — a leitura é o recurso, e o que se está fazendo é apagá-la.
     */
    public function markUnread(MarkFormSubmissionUnreadRequest $request, VolunteerApplication $volunteerApplication, MarkSubmissionAsUnread $markAsUnread): VolunteerApplicationResource
    {
        $markAsUnread->handle($volunteerApplication, $request->user(), $request->ip());

        return new VolunteerApplicationResource($volunteerApplication->fresh(['handledBy', 'readBy']));
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, VolunteerApplication $volunteerApplication): VolunteerApplicationResource
    {
        $volunteerApplication->status = FormSubmissionStatus::from($request->string('status')->value());
        $volunteerApplication->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $volunteerApplication->handled_by = $request->user()?->id;
        $volunteerApplication->handled_at = now();
        $volunteerApplication->save();

        return new VolunteerApplicationResource($volunteerApplication->fresh(['handledBy', 'readBy']));
    }
}
