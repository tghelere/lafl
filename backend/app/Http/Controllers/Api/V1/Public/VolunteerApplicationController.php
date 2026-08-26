<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Forms\CreateVolunteerApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreVolunteerApplicationRequest;
use App\Http\Resources\Public\FormSubmissionResource;
use App\Support\Honeypot;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class VolunteerApplicationController extends Controller
{
    public function store(StoreVolunteerApplicationRequest $request, CreateVolunteerApplication $action): JsonResponse
    {
        $result = Honeypot::triggered($request)
            ? Honeypot::decoySubmission()
            : $action->handle($request->toDto());

        return (new FormSubmissionResource($result))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
