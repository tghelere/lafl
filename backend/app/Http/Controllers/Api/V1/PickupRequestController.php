<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\PickupRequestListResource;
use App\Http\Resources\PickupRequestResource;
use App\Models\PickupRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PickupRequestController extends Controller
{
    use LogsSubmissionAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PickupRequest::class);

        $perPage = min($request->integer('per_page', 15), 100);

        // Ordenado por scheduled_for quando existir, para "agenda por data" (ver
        // docs/estrutura-site.md §4.2, tela Bazar) — o que ainda não tem data agendada vai
        // por último, ordenado por criação.
        $query = PickupRequest::query()->with('handledBy')
            ->orderByRaw('scheduled_for IS NULL')
            ->orderBy('scheduled_for')
            ->latest('created_at');

        $status = $request->filled('status') ? FormSubmissionStatus::tryFrom($request->string('status')->value()) : null;
        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return PickupRequestListResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        Gate::authorize('view', $pickupRequest);

        $pickupRequest->loadMissing('handledBy');

        $this->logAccess($pickupRequest, $request);

        return new PickupRequestResource($pickupRequest);
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, PickupRequest $pickupRequest): PickupRequestResource
    {
        $pickupRequest->status = FormSubmissionStatus::from($request->string('status')->value());
        $pickupRequest->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $pickupRequest->handled_by = $request->user()?->id;
        $pickupRequest->handled_at = now();
        $pickupRequest->save();

        return new PickupRequestResource($pickupRequest->fresh('handledBy'));
    }
}
