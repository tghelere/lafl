<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserAccountResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class UserController extends Controller
{
    /**
     * Busca por nome ou e-mail (`search`) e filtro por ativo/inativo (`status=active|
     * inactive`) — sem filtro, devolve todos.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $perPage = min($request->integer('per_page', 15), 100);
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->value();

        $query = User::query()->with('roles')->orderBy('name');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->inactive();
        }

        return UserAccountResource::collection($query->paginate($perPage));
    }

    public function store(StoreUserRequest $request, CreateUser $action): UserAccountResource
    {
        $user = $action->handle(
            $request->string('name')->value(),
            $request->string('email')->value(),
            $request->roles(),
        );

        return new UserAccountResource($user);
    }

    public function show(User $user): UserAccountResource
    {
        Gate::authorize('view', $user);

        return new UserAccountResource($user);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $action): UserAccountResource
    {
        /** @var User $actingUser */
        $actingUser = $request->user();

        $updated = $action->handle(
            $actingUser,
            $user,
            $request->string('name')->value(),
            $request->string('email')->value(),
            $request->roles(),
        );

        return new UserAccountResource($updated);
    }

    public function deactivate(Request $request, User $user, DeactivateUser $action): UserAccountResource
    {
        Gate::authorize('deactivate', $user);

        /** @var User $actingUser */
        $actingUser = $request->user();

        return new UserAccountResource($action->handle($actingUser, $user));
    }

    public function reactivate(User $user, ReactivateUser $action): UserAccountResource
    {
        Gate::authorize('reactivate', $user);

        return new UserAccountResource($action->handle($user));
    }
}
