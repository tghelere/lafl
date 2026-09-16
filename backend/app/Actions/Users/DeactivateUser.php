<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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

            // Link de definição de senha pendente (ou um futuro "esqueci minha senha" por
            // e-mail, broker 'users') não pode continuar utilizável para uma conta desativada
            // — GeneratePasswordLink já barra gerar um novo, mas um link gerado antes da
            // desativação continuaria válido até expirar sozinho sem isto.
            Password::broker('user_setup')->deleteToken($target);
            Password::broker('users')->deleteToken($target);

            return $target;
        });
    }
}
