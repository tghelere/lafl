<?php

declare(strict_types=1);

test('rota de health check responde 200', function (): void {
    $this->get('/up')->assertStatus(200);
});
