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
use App\Http\Resources\PickupRequestListResource;
use App\Http\Resources\PickupRequestResource;
use App\Models\PickupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PickupRequestController extends Controller
{
    use ListsFormSubmissions, LogsSubmissionAccess;

    public function index(IndexFormSubmissionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PickupRequest::class);

        // Ordenado pela chegada, como as outras quatro listagens — não mais por
        // `scheduled_for`. A agenda de coleta por data agendada continua fazendo sentido, mas
        // não como ordenação FIXA de uma caixa de entrada: o que a tela mostra primeiro tem de
        // ser o que chegou por último, senão um pedido novo nasce no meio da lista. A data
        // agendada segue na listagem como coluna. Ver docs/relatorio-sessao-25.md.
        $query = PickupRequest::query()->with(['handledBy', 'readBy'])->latest('created_at');

        return PickupRequestListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
    }

    public function show(Request $request, PickupRequest $pickupRequest, MarkSubmissionAsRead $markAsRead): PickupRequestResource
    {
        Gate::authorize('view', $pickupRequest);

        $this->logAccess($pickupRequest, $request);

        // Abrir o detalhe É a leitura — não existe botão "marcar como lido" (ver
        // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). Depois do log de
        // acesso, para que o registro do acesso não dependa de a escrita dar certo.
        $markAsRead->handle($pickupRequest, $request->user());

        $pickupRequest->loadMissing(['handledBy', 'readBy']);

        return new PickupRequestResource($pickupRequest);
    }

    /**
     * Devolve o registro ao estado "não lido" para a equipe inteira. `DELETE` sobre
     * `/read` — a leitura é o recurso, e o que se está fazendo é apagá-la.
     */
    public function markUnread(MarkFormSubmissionUnreadRequest $request, PickupRequest $pickupRequest, MarkSubmissionAsUnread $markAsUnread): PickupRequestResource
    {
        $markAsUnread->handle($pickupRequest, $request->user());

        return new PickupRequestResource($pickupRequest->fresh(['handledBy', 'readBy']));
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $pickupRequest->status = FormSubmissionStatus::from($request->string('status')->value());
        $pickupRequest->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $pickupRequest->handled_by = $request->user()?->id;
        $pickupRequest->handled_at = now();
        $pickupRequest->save();

        return new PickupRequestResource($pickupRequest->fresh(['handledBy', 'readBy']));
    }
}
