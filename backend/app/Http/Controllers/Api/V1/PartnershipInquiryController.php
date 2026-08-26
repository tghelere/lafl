<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\PartnershipInquiryListResource;
use App\Http\Resources\PartnershipInquiryResource;
use App\Models\PartnershipInquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PartnershipInquiryController extends Controller
{
    use LogsSubmissionAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PartnershipInquiry::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $query = PartnershipInquiry::query()->with('handledBy')->latest('created_at');

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

        return PartnershipInquiryListResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, PartnershipInquiry $partnershipInquiry): PartnershipInquiryResource
    {
        Gate::authorize('view', $partnershipInquiry);

        $partnershipInquiry->loadMissing('handledBy');

        $this->logAccess($partnershipInquiry, $request);

        return new PartnershipInquiryResource($partnershipInquiry);
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, PartnershipInquiry $partnershipInquiry): PartnershipInquiryResource
    {
        $partnershipInquiry->status = FormSubmissionStatus::from($request->string('status')->value());
        $partnershipInquiry->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $partnershipInquiry->handled_by = $request->user()?->id;
        $partnershipInquiry->handled_at = now();
        $partnershipInquiry->save();

        return new PartnershipInquiryResource($partnershipInquiry->fresh('handledBy'));
    }
}
