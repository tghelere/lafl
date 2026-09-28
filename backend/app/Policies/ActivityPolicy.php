<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * O log de auditoria é só de `super_admin` — nem `direcao`, que acessa todos os formulários
 * recebidos. Quem audita não pode ser quem é auditado: `direcao` aparece nas linhas do log, e dar
 * a ela a chave do log seria tirar do registro a única propriedade que o faz valer algo.
 *
 * Toda ability aqui é `false`, no mesmo desenho de App\Policies\UserPolicy: quem passa é só
 * `super_admin`, pelo `Gate::before` de AppServiceProvider::configureAuthorization(), e o método
 * abaixo nunca chega a rodar para esse papel.
 *
 * Não há `create`, `update` nem `delete` porque a tela é só leitura — e um log de auditoria que
 * se possa editar pelo painel não é um log de auditoria.
 */
final class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }
}
