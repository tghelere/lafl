<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Events\Auth\UserPasswordChanged;
use App\Models\User;
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

        UserPasswordChanged::dispatch($user);
    }
}
