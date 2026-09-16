<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Primeira definição de senha (via link de uso único gerado pelo super_admin) e futura
 * redefinição por e-mail (quando existir, ver docs/roadmap.md) reaproveitam o mesmo formato
 * de resposta: token inválido, expirado, e-mail que não confere ou usuário desativado dão
 * todos a mesma mensagem genérica — nunca dá para saber, pela resposta, qual dos quatro
 * aconteceu. Diferenciar herdaria só valor para quem já está tentando adivinhar tokens ou
 * e-mails de conta desativada.
 */
final class SetUserPassword
{
    public function handle(string $token, string $email, string $password): void
    {
        $status = Password::broker('user_setup')->reset(
            ['token' => $token, 'email' => $email, 'password' => $password],
            function (User $user, string $password): void {
                if ($user->deactivated_at !== null) {
                    throw ValidationException::withMessages([
                        'token' => ['Link inválido ou expirado.'],
                    ]);
                }

                $user->forceFill(['password' => $password])->save();

                activity('users')
                    ->causedBy($user)
                    ->performedOn($user)
                    ->event('password_set')
                    ->log('Senha definida pela primeira vez');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Link inválido ou expirado.'],
            ]);
        }
    }
}
