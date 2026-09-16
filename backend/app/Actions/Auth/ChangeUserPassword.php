<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Events\Auth\UserPasswordChanged;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangeUserPassword
{
    public function handle(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Senha atual incorreta.'],
            ]);
        }

        $user->forceFill(['password' => $newPassword])->save();

        // Laravel\Sanctum\Http\Middleware\AuthenticateSession lê o hash de senha em
        // Auth::guard('web')->user() (ver config('sanctum.guard')) para decidir se a sessão
        // atual continua válida — não em $request->user(), que em modo Sanctum resolve pelo
        // guard 'sanctum' (RequestGuard), com cache próprio, separado do cache do guard
        // 'web'. Sem isto, o guard 'web' ficava com a senha antiga em cache e a própria
        // sessão que troca a senha caía junto com as outras no fim da requisição.
        Auth::guard('web')->setUser($user);

        UserPasswordChanged::dispatch($user);
    }
}
