<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Forms\CreateProgramApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreProgramApplicationRequest;
use App\Http\Resources\Public\FormSubmissionResource;
use App\Support\Honeypot;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class ProgramApplicationController extends Controller
{
    public function store(StoreProgramApplicationRequest $request, CreateProgramApplication $action): JsonResponse
    {
        $result = Honeypot::triggered($request)
            ? Honeypot::decoySubmission()
            : $action->handle($request->toDto());

        return (new FormSubmissionResource($result))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
