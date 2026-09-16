<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Sem e-mail configurado no lançamento — a URL devolvida na resposta é copiada pelo
 * super_admin e enviada por fora (WhatsApp), ver docs/levantamento-painel.md, item 2. Nunca
 * fica em log nenhum além desta resposta HTTP de uma vez só: o evento de auditoria registra
 * que um link foi gerado, nunca o token nem a URL (ver App\Providers\AppServiceProvider —
 * regra 8 do CLAUDE.md).
 */
final class GeneratePasswordLink
{
    public function handle(User $user): string
    {
        if ($user->deactivated_at !== null) {
            throw ValidationException::withMessages([
                'user' => ['Não é possível gerar link para um usuário desativado.'],
            ]);
        }

        // Gerar um token novo já invalida qualquer um anterior — DatabaseTokenRepository
        // apaga a linha existente daquele e-mail antes de inserir a nova (ver
        // vendor/laravel/framework, Illuminate\Auth\Passwords\DatabaseTokenRepository::create).
        $token = Password::broker('user_setup')->createToken($user);

        $baseUrl = rtrim((string) config('forms.admin_base_url'), '/');
        $url = sprintf('%s/definir-senha?token=%s', $baseUrl, $token);

        activity('users')
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->event('password_link_generated')
            ->log('Link de definição de senha gerado');

        return $url;
    }
}
