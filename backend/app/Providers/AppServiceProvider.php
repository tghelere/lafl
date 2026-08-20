<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configureRateLimiting();
        $this->configureAuthorization();
    }

    /**
     * super_admin sempre passa — é a única conta com acesso total a todo recurso (ver
     * docs/estrutura-site.md §4.4). Policies individuais não precisam repetir esse caso.
     */
    private function configureAuthorization(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasRole(Role::SuperAdmin->value) ? true : null);
    }

    private function configureRateLimiting(): void
    {
        // Mais restrito que o throttle padrão da API — login é o alvo mais óbvio de força
        // bruta (ver docs/arquitetura.md, seção Segurança).
        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });
    }
}
