<?php

declare(strict_types=1);

test('requisição não autenticada à API sem header Accept devolve 401 em JSON, nunca 500', function (): void {
    // Sem Accept: application/json de propósito — é o caso de curl, robô ou navegador abrindo
    // a URL direto (getJson() do Pest sempre manda esse header, o que mascarava o bug: o
    // middleware 'auth' tentava redirecionar para a rota nomeada 'login', que não existe numa
    // API REST pura sem view, e isso virava 500 ("Route [login] not defined") em vez de 401.
    // Corrigido em bootstrap/app.php com redirectGuestsTo(null) — ver
    // docs/tarefas/05-correcoes-de-codigo.md.
    $this->get('/api/v1/users')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});
