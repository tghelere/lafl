<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
