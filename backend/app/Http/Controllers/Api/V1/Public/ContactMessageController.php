<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Forms\CreateContactMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreContactMessageRequest;
use App\Http\Resources\Public\FormSubmissionResource;
use App\Support\Honeypot;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request, CreateContactMessage $action): JsonResponse
    {
        $result = Honeypot::triggered($request)
            ? Honeypot::decoySubmission()
            : $action->handle($request->toDto());

        return (new FormSubmissionResource($result))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
