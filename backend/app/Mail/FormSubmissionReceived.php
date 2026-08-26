<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\FormSubmissionType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notificação ao setor responsável a cada formulário recebido (ver docs/estrutura-site.md
 * §2.3). Nunca carrega dado pessoal — só tipo, data e um link para o painel administrativo
 * (a tela em si ainda não existe, ver docs/roadmap.md). E-mail não é canal seguro: o
 * destinatário pode encaminhar sem pensar, então o conteúdo não pode depender de sigilo.
 */
final class FormSubmissionReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly FormSubmissionType $type,
        public readonly string $submittedAt,
        public readonly string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Novo formulário recebido: {$this->type->label()}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.form-submission-received',
            with: [
                'typeLabel' => $this->type->label(),
                'submittedAt' => $this->submittedAt,
                'adminUrl' => $this->adminUrl,
            ],
        );
    }
}
