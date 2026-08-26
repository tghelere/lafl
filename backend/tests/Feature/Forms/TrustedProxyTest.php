<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use App\Services\BlindIndexService;

/**
 * O cliente de teste do Laravel já conecta como 127.0.0.1, que está em TRUSTED_PROXIES (ver
 * bootstrap/app.php) — então X-Forwarded-For deveria ser respeitado. Isso prova que o proxy
 * servidor-a-servidor do Nuxt (frontend-site/server/api/forms/[tipo].post.ts) consegue
 * repassar o IP real do visitante para o rate limit e o ip_hash funcionarem por visitante,
 * não por "todo mundo que passa pelo Nuxt".
 */
test('IP encaminhado por proxy confiável é usado, não o IP de conexão direta', function (): void {
    $this->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->postJson('/api/v1/public/contact-messages', [
            'name' => 'Visitante',
            'email' => 'visitante@example.com',
            'subject' => 'Teste',
            'message' => 'Mensagem de teste.',
            'consent' => true,
        ])->assertCreated();

    $message = ContactMessage::first();
    $expectedHash = app(BlindIndexService::class)->hash('203.0.113.7');

    expect($message->ip_hash)->toBe($expectedHash);
});
