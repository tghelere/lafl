<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sessão aberta do usuário desativado não é derrubada por aqui, de propósito — perde o
 * acesso sozinha na próxima requisição (ver App\Http\Middleware\EnsureUserIsActive), sem
 * precisar rastrear ou revogar sessão por sessão.
 */
final class DeactivateUser
{
    public function __construct(private readonly AssertLastActiveSuperAdminSurvives $assertLastActiveSuperAdminSurvives) {}

    public function handle(User $actingUser, User $target): User
    {
        if ($target->is($actingUser)) {
            throw ValidationException::withMessages([
                'user' => ['Não é possível desativar a própria conta.'],
            ]);
        }

        return DB::transaction(function () use ($target): User {
            $this->assertLastActiveSuperAdminSurvives->handle($target);

            $target->forceFill(['deactivated_at' => now()])->save();

            return $target;
        });
    }
}
