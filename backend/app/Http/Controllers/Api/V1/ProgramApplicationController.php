<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\ProgramApplicationListResource;
use App\Http\Resources\ProgramApplicationResource;
use App\Models\ProgramApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ProgramApplicationController extends Controller
{
    use LogsSubmissionAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ProgramApplication::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $query = ProgramApplication::query()->with('handledBy')->latest('created_at');

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

        return ProgramApplicationListResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, ProgramApplication $programApplication): ProgramApplicationResource
    {
        Gate::authorize('view', $programApplication);

        $programApplication->loadMissing('handledBy');

        $this->logAccess($programApplication, $request);

        return new ProgramApplicationResource($programApplication);
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, ProgramApplication $programApplication): ProgramApplicationResource
    {
        $programApplication->status = FormSubmissionStatus::from($request->string('status')->value());
        $programApplication->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $programApplication->handled_by = $request->user()?->id;
        $programApplication->handled_at = now();
        $programApplication->save();

        return new ProgramApplicationResource($programApplication->fresh('handledBy'));
    }
}
