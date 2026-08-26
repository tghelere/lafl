<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Dashboard\GetPendingFormSubmissionCounts;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function show(Request $request, GetPendingFormSubmissionCounts $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $action->handle($user)]);
    }
}
