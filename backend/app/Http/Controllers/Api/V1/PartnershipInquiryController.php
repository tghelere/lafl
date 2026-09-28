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
use App\Http\Resources\PartnershipInquiryListResource;
use App\Http\Resources\PartnershipInquiryResource;
use App\Models\PartnershipInquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PartnershipInquiryController extends Controller
{
    use ListsFormSubmissions, LogsSubmissionAccess;

    public function index(IndexFormSubmissionsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PartnershipInquiry::class);

        $query = PartnershipInquiry::query()->with(['handledBy', 'readBy'])->latest('created_at');

        return PartnershipInquiryListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
    }

    public function show(Request $request, PartnershipInquiry $partnershipInquiry, MarkSubmissionAsRead $markAsRead): PartnershipInquiryResource
    {
        Gate::authorize('view', $partnershipInquiry);

        $this->logAccess($partnershipInquiry, $request);

        // Abrir o detalhe É a leitura — não existe botão "marcar como lido" (ver
        // docs/decisoes/0021-leitura-separada-do-status-de-atendimento.md). Depois do log de
        // acesso, para que o registro do acesso não dependa de a escrita dar certo.
        $markAsRead->handle($partnershipInquiry, $request->user());

        $partnershipInquiry->loadMissing(['handledBy', 'readBy']);

        return new PartnershipInquiryResource($partnershipInquiry);
    }

    /**
     * Devolve o registro ao estado "não lido" para a equipe inteira. `DELETE` sobre
     * `/read` — a leitura é o recurso, e o que se está fazendo é apagá-la.
     */
    public function markUnread(MarkFormSubmissionUnreadRequest $request, PartnershipInquiry $partnershipInquiry, MarkSubmissionAsUnread $markAsUnread): PartnershipInquiryResource
    {
        $markAsUnread->handle($partnershipInquiry, $request->user(), $request->ip());

        return new PartnershipInquiryResource($partnershipInquiry->fresh(['handledBy', 'readBy']));
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, PartnershipInquiry $partnershipInquiry): PartnershipInquiryResource
    {
        $partnershipInquiry->status = FormSubmissionStatus::from($request->string('status')->value());
        $partnershipInquiry->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $partnershipInquiry->handled_by = $request->user()?->id;
        $partnershipInquiry->handled_at = now();
        $partnershipInquiry->save();

        return new PartnershipInquiryResource($partnershipInquiry->fresh(['handledBy', 'readBy']));
    }
}
