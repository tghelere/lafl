<?php

declare(strict_types=1);

use App\Enums\FormSubmissionType;
use App\Mail\FormSubmissionReceived;
use App\Providers\AppServiceProvider;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\NullTransport;

/**
 * O provider já rodou quando o teste começa, então cada caso reconfigura e chama o boot de
 * novo — é o mesmo caminho que a aplicação percorre ao subir com a variável definida.
 * O mailer `array` do ambiente de teste guarda as mensagens enviadas, o que permite ler o
 * destinatário FINAL, depois do reendereçamento, e não só o que a Action pediu.
 */
function bootWithAlwaysTo(?string $address): void
{
    config(['mail.always_to' => $address]);

    // O mailer já resolvido guarda o `alwaysTo` anterior; descartar força a reconstrução com
    // a configuração nova.
    app()->forgetInstance('mailer');
    app('mail.manager')->forgetMailers();

    (new AppServiceProvider(app()))->boot();
}

/**
 * @return Collection<int, SentMessage>
 */
function sentMessages(): Collection
{
    $transport = Mail::mailer()->getSymfonyTransport();

    expect($transport)->not->toBeInstanceOf(NullTransport::class);

    return $transport->messages();
}

afterEach(function (): void {
    bootWithAlwaysTo(null);
});

test('sem MAIL_ALWAYS_TO o e-mail vai para o destinatário pedido', function (): void {
    bootWithAlwaysTo(null);

    Mail::to('financeiro@exemplo.org.br')->send(
        new FormSubmissionReceived(FormSubmissionType::ContactMessage, '17/09/2026 10:00', 'https://painel.exemplo.org.br/admin/contact-messages/abc'),
    );

    $to = sentMessages()->first()->getOriginalMessage()->getTo();

    expect($to)->toHaveCount(1)
        ->and($to[0]->getAddress())->toBe('financeiro@exemplo.org.br');
});

test('com MAIL_ALWAYS_TO todo e-mail é reendereçado para o endereço único', function (): void {
    bootWithAlwaysTo('homologacao@exemplo.org.br');

    Mail::to('financeiro@exemplo.org.br')
        ->cc('direcao@exemplo.org.br')
        ->bcc('auditoria@exemplo.org.br')
        ->send(new FormSubmissionReceived(FormSubmissionType::ContactMessage, '17/09/2026 10:00', 'https://painel.exemplo.org.br/admin/contact-messages/abc'));

    $message = sentMessages()->first()->getOriginalMessage();

    expect($message->getTo())->toHaveCount(1)
        ->and($message->getTo()[0]->getAddress())->toBe('homologacao@exemplo.org.br')
        ->and($message->getCc())->toBe([])
        ->and($message->getBcc())->toBe([]);
});

test('o reendereçamento alcança o e-mail dos formulários públicos', function (): void {
    bootWithAlwaysTo('homologacao@exemplo.org.br');

    $this->postJson('/api/v1/public/contact-messages', [
        'name' => 'Fernanda Costa',
        'email' => 'fernanda@example.com',
        'subject' => 'Elogio',
        'message' => 'Parabéns pelo trabalho.',
        'consent' => true,
    ])->assertCreated();

    $to = sentMessages()->first()->getOriginalMessage()->getTo();

    expect($to)->toHaveCount(1)
        ->and($to[0]->getAddress())->toBe('homologacao@exemplo.org.br');
});
