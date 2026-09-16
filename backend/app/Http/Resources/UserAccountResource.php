<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource da gestão administrativa de usuários (/api/v1/users) — distinto de
 * App\Http\Resources\UserResource (usado em /auth/*, para o próprio usuário ver os dados da
 * própria sessão). Sem nenhum campo sensível: nunca senha, remember_token ou qualquer coisa
 * de 2FA, nem o campo derivado `two_factor_enabled` que UserResource expõe — aqui é sempre
 * sobre a conta de outra pessoa, não a própria.
 *
 * @mixin User
 */
final class UserAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Nunca o id sequencial — rotas e payloads usam apenas o uuid (ver CLAUDE.md).
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames()->values(),
            'active' => $this->deactivated_at === null,
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
