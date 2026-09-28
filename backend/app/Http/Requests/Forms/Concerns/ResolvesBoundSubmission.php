<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Pega o formulário recebido já resolvido pelo route model binding, seja qual for o nome do
 * parâmetro da rota (`{contactMessage}`, `{pickupRequest}`, ...). É o que permite um FormRequest
 * só servir às cinco rotas de um mesmo verbo, em vez de cinco classes idênticas trocando só o
 * nome do parâmetro.
 */
trait ResolvesBoundSubmission
{
    protected function boundSubmission(): ?Model
    {
        foreach ($this->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }
}
