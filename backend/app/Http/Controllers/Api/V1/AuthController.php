<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ChangeUserPassword;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\SetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginUser $action): UserResource
    {
        $user = $action->handle($request->string('email')->value(), $request->string('password')->value());

        return new UserResource($user);
    }

    public function logout(LogoutUser $action): Response
    {
        $action->handle();

        return response()->noContent();
    }

    public function user(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    public function updatePassword(ChangePasswordRequest $request, ChangeUserPassword $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle(
            $user,
            $request->string('current_password')->value(),
            $request->string('password')->value(),
        );

        return response()->noContent();
    }

    public function setPassword(SetPasswordRequest $request, SetUserPassword $action): Response
    {
        $action->handle(
            $request->string('token')->value(),
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        return response()->noContent();
    }
}
