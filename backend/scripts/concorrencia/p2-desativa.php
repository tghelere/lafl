<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

/**
 * Cenário 2: caso misto. Enquanto P1 remove o papel de B, este processo tenta DESATIVAR A ao
 * mesmo tempo — prova que a proteção serializa entre as duas Actions chamadoras
 * (UpdateUser e DeactivateUser), não só dentro de uma delas.
 */
use App\Actions\Users\DeactivateUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

$actor = User::where('email', 'super-b'.CORRIDA_EMAIL_DOMAIN)->firstOrFail();   // B
$target = User::where('email', 'super-a'.CORRIDA_EMAIL_DOMAIN)->firstOrFail();  // A

if (! waitForSignal('p1-locked', 30.0)) {
    say('P2', 'P1 não sinalizou a tempo; abortando');
    exit(1);
}

say('P2', 'P1 está com a trava; vou tentar DESATIVAR A (caminho real: DeactivateUser)');
signal('p2-attempting');

try {
    app(DeactivateUser::class)->handle($actor, $target);

    say('P2', 'RESULTADO: desativação PERMITIDA (sem exceção)');
} catch (ValidationException $e) {
    $messages = array_merge(...array_values($e->errors()));
    say('P2', 'RESULTADO: desativação RECUSADA — '.implode(' ', $messages));
}

signal('p2-done');
