<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\ListsFormSubmissions;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\IndexFormSubmissionsRequest;
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

        $query = ContactMessage::query()->with('handledBy')->latest('created_at');

        return ContactMessageListResource::collection(
            $this->applySubmissionFilters($query, $request)->paginate($this->submissionsPerPage($request)),
        );
    }

    public function show(Request $request, ContactMessage $contactMessage): ContactMessageResource
    {
        Gate::authorize('view', $contactMessage);

        $contactMessage->loadMissing('handledBy');

        $this->logAccess($contactMessage, $request);

        return new ContactMessageResource($contactMessage);
    }

    public function updateStatus(UpdateFormSubmissionStatusRequest $request, ContactMessage $contactMessage): ContactMessageResource
    {
        $contactMessage->status = FormSubmissionStatus::from($request->string('status')->value());
        $contactMessage->internal_note = $request->filled('internal_note') ? $request->string('internal_note')->value() : null;
        $contactMessage->handled_by = $request->user()?->id;
        $contactMessage->handled_at = now();
        $contactMessage->save();

        return new ContactMessageResource($contactMessage->fresh('handledBy'));
    }
}
