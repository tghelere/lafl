<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditoria de acesso — nunca registra valor descriptografado (ver docs/protecao-de-dados.md).
 */
final class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof Model) {
            return;
        }

        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->event('login')
            ->log('Login realizado');
    }
}
