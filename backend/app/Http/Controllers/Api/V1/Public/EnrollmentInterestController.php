<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Forms\CreateEnrollmentInterest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreEnrollmentInterestRequest;
use App\Http\Resources\Public\FormSubmissionResource;
use App\Support\Honeypot;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class EnrollmentInterestController extends Controller
{
    public function store(StoreEnrollmentInterestRequest $request, CreateEnrollmentInterest $action): JsonResponse
    {
        // Resposta idêntica (corpo e status) com ou sem honeypot disparado — ver
        // App\Support\Honeypot. 201 explícito nos dois ramos, sem depender do
        // `wasRecentlyCreated` implícito do Eloquent.
        $result = Honeypot::triggered($request)
            ? Honeypot::decoySubmission()
            : $action->handle($request->toDto());

        return (new FormSubmissionResource($result))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
