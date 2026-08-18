<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function handle(string $email, string $password): User
    {
        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Regenera o ID de sessão para evitar fixation — a sessão pré-login não deve
        // continuar válida após a troca de identidade.
        Session::regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }
}
