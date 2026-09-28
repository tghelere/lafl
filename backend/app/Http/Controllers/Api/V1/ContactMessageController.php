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
use App\Http\Resources\ContactMessageListResource;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ContactMessageController extends Controller
{
    use ListsFormSubmissions, LogsSubmissionAccess;

    public function index(IndexFormSubmissionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ContactMessage::class);

        $query = ContactMessage::query()->with(['handledBy', 'readBy'])->latest('created_at');

        return ContactMessageListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
    }

    public function show(Request $request, ContactMessage $contactMessage, MarkSubmissionAsRead $markAsRead): ContactMessageResource
    {
        Gate::authorize('view', $contactMessage);

        $this->logAccess($contactMessage, $request);

        // Abrir o detalhe É a leitura — não existe botão "marcar como lido" (ver
        // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). Depois do log de
        // acesso, para que o registro do acesso não dependa de a escrita dar certo.
        $markAsRead->handle($contactMessage, $request->user());

        $contactMessage->loadMissing(['handledBy', 'readBy']);

        return new ContactMessageResource($contactMessage);
    }

    /**
     * Devolve o registro ao estado "não lido" para a equipe inteira. `DELETE` sobre
     * `/read` — a leitura é o recurso, e o que se está fazendo é apagá-la.
     */
    public function markUnread(MarkFormSubmissionUnreadRequest $request, ContactMessage $contactMessage, MarkSubmissionAsUnread $markAsUnread): ContactMessageResource
    {
        $markAsUnread->handle($contactMessage, $request->user());

        return new ContactMessageResource($contactMessage->fresh(['handledBy', 'readBy']));
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, ContactMessage $contactMessage): ContactMessageResource
    {
        $contactMessage->status = FormSubmissionStatus::from($request->string('status')->value());
        $contactMessage->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $contactMessage->handled_by = $request->user()?->id;
        $contactMessage->handled_at = now();
        $contactMessage->save();

        return new ContactMessageResource($contactMessage->fresh(['handledBy', 'readBy']));
    }
}
