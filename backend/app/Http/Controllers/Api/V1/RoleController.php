<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Lista os papéis do enum para popular o seletor da tela de usuários (ainda não existe, ver
 * docs/roadmap.md) — nome e descrição vêm só de App\Enums\Role, único lugar que os define.
 * Autorizado pela mesma ability de listar usuários (App\Policies\UserPolicy::viewAny): quem
 * pode ver a lista de usuários é quem precisa saber quais papéis existem para atribuir.
 */
final class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $roles = collect(Role::cases())->map(fn (Role $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
        ])->values();

        return response()->json(['data' => $roles]);
    }
}
