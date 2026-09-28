<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\ListsFormSubmissions;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\IndexFormSubmissionsRequest;
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

        $query = PartnershipInquiry::query()->with('handledBy')->latest('created_at');

        return PartnershipInquiryListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
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
