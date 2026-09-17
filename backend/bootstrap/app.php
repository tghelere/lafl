<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Sem isto, o middleware 'auth' tenta redirecionar quem não está autenticado para uma
        // rota nomeada 'login' — que não existe (API REST pura, sem view, ver CLAUDE.md) — e
        // isso vira 500 ("Route [login] not defined") em vez de 401 sempre que a requisição
        // não pede JSON explicitamente (sem `Accept: application/json`, ex.: curl, robô,
        // navegador abrindo a URL direto). `redirectGuestsTo(null)` faz o guard devolver null
        // em vez de tentar montar essa URL, e o `shouldRenderJsonWhen` abaixo garante que toda
        // rota `api/*` responde 401 em JSON de qualquer forma.
        $middleware->redirectGuestsTo(null);

        // O site público (Nuxt) faz proxy servidor-a-servidor dos seis formulários (ver
        // frontend-site/server/api/forms/[tipo].post.ts) para que o "Redirect para
        // /obrigado/:tipo" e o reaproveitamento de erro funcionem sem JavaScript — a API em
        // si continua REST puro, sem view (ver CLAUDE.md). Sem confiar nesse proxy,
        // `$request->ip()` veria sempre o IP do Nuxt, não do visitante, e o rate limit por
        // IP (ver App\Providers\AppServiceProvider) ficaria inútil. Confiar só em '*' abriria
        // brecha: qualquer chamada direta à API poderia forjar X-Forwarded-For para escapar
        // do limite — por isso só os IPs em TRUSTED_PROXIES (loopback por padrão, o mesmo
        // host do Nuxt em dev) são confiados.
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))));

        // 'active': usuário desativado perde a sessão aberta na requisição seguinte (ver
        // App\Http\Middleware\EnsureUserIsActive). 'auth.session': Sanctum já embute suporte a
        // isto para o caso de troca de senha — compara o hash de senha guardado na sessão com
        // o hash atual do usuário a cada requisição e desloga quando divergem, o que cobre
        // tanto a troca autenticada (PUT /auth/password) quanto a definição de senha por link
        // (POST /auth/set-password), sem precisar de código próprio (ver
        // App\Actions\Users\SetUserPassword). Confirmado que se aplica em modo SPA: o guard
        // 'web' configurado em config/sanctum.php é um SessionGuard de verdade, que é
        // exatamente o que Laravel\Sanctum\Http\Middleware\AuthenticateSession espera.
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'auth.session' => AuthenticateSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
