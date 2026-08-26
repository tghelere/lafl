<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Forms\CreatePartnershipInquiry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StorePartnershipInquiryRequest;
use App\Http\Resources\Public\FormSubmissionResource;
use App\Support\Honeypot;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PartnershipInquiryController extends Controller
{
    public function store(StorePartnershipInquiryRequest $request, CreatePartnershipInquiry $action): JsonResponse
    {
        $result = Honeypot::triggered($request)
            ? Honeypot::decoySubmission()
            : $action->handle($request->toDto());

        return (new FormSubmissionResource($result))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
