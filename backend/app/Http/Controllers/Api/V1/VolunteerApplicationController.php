<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\VolunteerApplicationListResource;
use App\Http\Resources\VolunteerApplicationResource;
use App\Models\VolunteerApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class VolunteerApplicationController extends Controller
{
    use LogsSubmissionAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', VolunteerApplication::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $query = VolunteerApplication::query()->with('handledBy')->latest('created_at');

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

        return VolunteerApplicationListResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, VolunteerApplication $volunteerApplication): VolunteerApplicationResource
    {
        Gate::authorize('view', $volunteerApplication);

        $volunteerApplication->loadMissing('handledBy');

        $this->logAccess($volunteerApplication, $request);

        return new VolunteerApplicationResource($volunteerApplication);
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, VolunteerApplication $volunteerApplication): VolunteerApplicationResource
    {
        $volunteerApplication->status = FormSubmissionStatus::from($request->string('status')->value());
        $volunteerApplication->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $volunteerApplication->handled_by = $request->user()?->id;
        $volunteerApplication->handled_at = now();
        $volunteerApplication->save();

        return new VolunteerApplicationResource($volunteerApplication->fresh('handledBy'));
    }
}
