<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

/**
 * Cenário 1: remoção cruzada de papel. Enquanto P1 remove o super_admin de B, este processo
 * tenta remover o super_admin de A ao mesmo tempo — o caso "dois super_admins removendo o
 * papel um do outro simultaneamente" citado no comentário de
 * App\Actions\Users\AssertLastActiveSuperAdminSurvives.
 */
use App\Actions\Users\UpdateUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

$actor = User::where('email', 'super-b'.CORRIDA_EMAIL_DOMAIN)->firstOrFail();   // B
$target = User::where('email', 'super-a'.CORRIDA_EMAIL_DOMAIN)->firstOrFail();  // A

if (! waitForSignal('p1-locked', 30.0)) {
    say('P2', 'P1 não sinalizou a tempo; abortando');
    exit(1);
}

say('P2', 'P1 está com a trava; vou tentar remover super_admin de A (caminho real: UpdateUser)');
signal('p2-attempting');

try {
    app(UpdateUser::class)->handle(
        actingUser: $actor,
        target: $target,
        name: $target->name,
        email: $target->email,
        roles: [],
    );

    say('P2', 'RESULTADO: operação PERMITIDA (sem exceção)');
} catch (ValidationException $e) {
    say('P2', 'RESULTADO: operação RECUSADA — '.implode(' ', $e->errors()['roles'] ?? ['(sem mensagem em roles)']));
}

signal('p2-done');
