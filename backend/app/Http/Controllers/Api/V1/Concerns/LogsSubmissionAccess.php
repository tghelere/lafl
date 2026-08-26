<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Auditoria de leitura, não só de escrita (ver docs/protecao-de-dados.md, "Auditoria": "quem
 * acessou ficha de assistido, quando, de qual IP" — o mesmo princípio vale para dado de
 * formulário recebido, mesmo o titular sendo adulto). `LogsActivity` (via
 * App\Models\Concerns\IsFormSubmission) já cobre criação e alteração automaticamente; leitura
 * não dispara evento de model, por isso precisa deste log manual, chamado explicitamente em
 * todo `show()` administrativo.
 *
 * Nunca grava o valor descriptografado — só uuid do registro (via `performedOn`), quem
 * acessou e de qual IP.
 */
trait LogsSubmissionAccess
{
    protected function logAccess(Model $submission, Request $request): void
    {
        activity('forms')
            ->causedBy($request->user())
            ->performedOn($submission)
            ->withProperties(['ip' => $request->ip()])
            ->event('viewed')
            ->log('Detalhe de formulário acessado');
    }
}
