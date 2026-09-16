<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FormSubmissionType;
use App\Models\Page;
use App\Models\TransparencyDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
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
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'access' => $this->accessMap($this->resource),
        ];
    }

    /**
     * viewAny de cada recurso administrativo, calculado pelas Policies via `$user->can()` —
     * nunca reimplementado aqui, só agregado. O front ainda não consome isto (ver
     * docs/roadmap.md, tela de usuários); existe para a navegação decidir o que mostrar sem
     * hardcodar papel por papel do lado do cliente.
     *
     * @return array<string, bool>
     */
    private function accessMap(User $user): array
    {
        $map = [
            'pages' => $user->can('viewAny', Page::class),
            'transparency-documents' => $user->can('viewAny', TransparencyDocument::class),
            'users' => $user->can('viewAny', User::class),
        ];

        foreach (FormSubmissionType::cases() as $type) {
            $map[$type->adminResourceSlug()] = $user->can('viewAny', $type->modelClass());
        }

        return $map;
    }
}
