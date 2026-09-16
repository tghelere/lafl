<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function __construct(private readonly LogoutUser $logoutUser) {}

    /**
     * A mensagem de conta desativada só aparece depois de confirmar a senha certa — senha
     * errada devolve sempre a mesma mensagem genérica, tenha a conta papel nenhum, esteja
     * ativa ou desativada. Sem essa ordem, a mensagem específica vira um oráculo: dá pra
     * descobrir por tentativa e erro quais e-mails têm conta desativada sem nunca acertar a
     * senha.
     */
    public function handle(string $email, string $password): User
    {
        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if ($user->deactivated_at !== null) {
            // Attempt() já autenticou e iniciou sessão — desfaz com a mesma limpeza de um
            // logout explícito antes de recusar.
            $this->logoutUser->handle();

            throw ValidationException::withMessages([
                'email' => ['Esta conta foi desativada. Fale com a direção.'],
            ]);
        }

        // Regenera o ID de sessão para evitar fixation — a sessão pré-login não deve
        // continuar válida após a troca de identidade.
        Session::regenerate();

        return $user;
    }
}
