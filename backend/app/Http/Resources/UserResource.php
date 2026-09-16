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
     * nunca reimplementado aqui, só agregado. É o que o painel usa para decidir menu e
     * guarda de tela (ver frontend-admin/src/components/AppSidebar.vue e AppLayout.vue), sem
     * hardcodar papel por papel do lado do cliente. Quem barra de fato continua sendo a
     * Policy: acrescentar recurso aqui sem Policy correspondente não concede nada.
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
