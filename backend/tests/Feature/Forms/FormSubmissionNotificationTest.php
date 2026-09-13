<?php

declare(strict_types=1);

use App\Enums\FormSubmissionType;
use App\Mail\FormSubmissionReceived;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;

test('envio de formulário enfileira e-mail de notificação ao setor responsável', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/public/contact-messages', [
        'name' => 'Fernanda Costa',
        'email' => 'fernanda@example.com',
        'subject' => 'Elogio',
        'message' => 'Parabéns pelo trabalho.',
        'consent' => true,
    ])->assertCreated();

    $message = ContactMessage::first();

    Mail::assertQueued(FormSubmissionReceived::class, function (FormSubmissionReceived $mail) use ($message) {
        return $mail->type === FormSubmissionType::ContactMessage
            && $mail->adminUrl === "http://localhost:5173/contact-messages/{$message->uuid}"
            && $mail->hasTo(config('forms.notification_recipients.contact_message'));
    });
});

test('e-mail de notificação nunca contém dado pessoal do formulário', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/public/contact-messages', [
        'name' => 'Fernanda Costa',
        'email' => 'fernanda@example.com',
        'subject' => 'Assunto sigiloso',
        'message' => 'Conteúdo confidencial da mensagem.',
        'consent' => true,
    ])->assertCreated();

    Mail::assertQueued(FormSubmissionReceived::class, function (FormSubmissionReceived $mail) {
        $rendered = $mail->render();

        expect($rendered)
            ->not->toContain('Fernanda Costa')
            ->not->toContain('fernanda@example.com')
            ->not->toContain('Assunto sigiloso')
            ->not->toContain('Conteúdo confidencial');

        return true;
    });
});

test('honeypot disparado não enfileira e-mail nenhum', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/public/contact-messages', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'subject' => 'x',
        'message' => 'x',
        'consent' => true,
        'website' => 'https://bot.example',
    ])->assertCreated();

    Mail::assertNothingQueued();
});

test('destinatário vem de config, um por tipo de formulário', function (): void {
    expect(config('forms.notification_recipients.program_application'))->not->toBe(config('forms.notification_recipients.contact_message'));
});
