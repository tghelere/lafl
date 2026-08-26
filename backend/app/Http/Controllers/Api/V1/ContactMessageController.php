<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FormSubmissionStatus;
use App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\UpdateFormSubmissionStatusRequest;
use App\Http\Resources\ContactMessageListResource;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ContactMessageController extends Controller
{
    use LogsSubmissionAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ContactMessage::class);

        $perPage = min($request->integer('per_page', 15), 100);

        $query = ContactMessage::query()->with('handledBy')->latest('created_at');

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

        return ContactMessageListResource::collection($query->paginate($perPage));
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
