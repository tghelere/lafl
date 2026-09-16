<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplicado junto de `auth:sanctum` nas rotas autenticadas (ver routes/api_v1.php) — roda
 * depois dele, então `$request->user()` já está resolvido. Desativar um usuário não derruba
 * sozinho uma sessão já aberta (o cookie continua válido); sem este middleware, a pessoa
 * continuaria acessando tudo até o cookie expirar. Mesma limpeza de sessão do logout
 * explícito (App\Actions\Auth\LogoutUser), para não deixar cookie nem CSRF token velhos.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->deactivated_at !== null) {
            Auth::guard('web')->logout();
            Session::invalidate();
            Session::regenerateToken();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
