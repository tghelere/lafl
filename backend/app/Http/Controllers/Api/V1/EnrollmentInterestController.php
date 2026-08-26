<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\EnrollmentInterestListResource;
use App\Http\Resources\EnrollmentInterestResource;
use App\Models\EnrollmentInterest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class EnrollmentInterestController extends Controller
{
    use LogsSubmissionAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', EnrollmentInterest::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $query = EnrollmentInterest::query()->with('handledBy')->latest('created_at');

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

        return EnrollmentInterestListResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, EnrollmentInterest $enrollmentInterest): EnrollmentInterestResource
    {
        Gate::authorize('view', $enrollmentInterest);

        $enrollmentInterest->loadMissing('handledBy');

        $this->logAccess($enrollmentInterest, $request);

        return new EnrollmentInterestResource($enrollmentInterest);
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, EnrollmentInterest $enrollmentInterest): EnrollmentInterestResource
    {
        $enrollmentInterest->status = FormSubmissionStatus::from($request->string('status')->value());
        $enrollmentInterest->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $enrollmentInterest->handled_by = $request->user()?->id;
        $enrollmentInterest->handled_at = now();
        $enrollmentInterest->save();

        return new EnrollmentInterestResource($enrollmentInterest->fresh('handledBy'));
    }
}
