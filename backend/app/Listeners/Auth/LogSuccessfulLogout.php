<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditoria de acesso — nunca registra valor descriptografado (ver docs/protecao-de-dados.md).
 */
final class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        if (! $event->user instanceof Model) {
            return;
        }

        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->event('logout')
            ->log('Logout realizado');
    }
}
