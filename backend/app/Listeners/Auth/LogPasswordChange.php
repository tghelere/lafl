<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\UserPasswordChanged;

/**
 * Auditoria de acesso — nunca registra valor descriptografado (ver docs/protecao-de-dados.md).
 */
final class LogPasswordChange
{
    public function handle(UserPasswordChanged $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->event('password_changed')
            ->log('Senha alterada');
    }
}
