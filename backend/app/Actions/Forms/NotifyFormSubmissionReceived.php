<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Enums\FormSubmissionType;
use App\Mail\FormSubmissionReceived;
use App\Support\InstitutionalTime;
use Illuminate\Support\Facades\Mail;

/**
 * Chamada pelas seis Actions de criação, sempre depois do save() — nunca no ramo de honeypot
 * (ver App\Support\Honeypot), que não deve gerar notificação nenhuma.
 */
final class NotifyFormSubmissionReceived
{
    public function handle(FormSubmissionType $type, string $uuid): void
    {
        $recipient = config("forms.notification_recipients.{$type->value}");
        $adminUrl = rtrim((string) config('forms.admin_base_url'), '/')."/admin/{$type->adminResourceSlug()}/{$uuid}";

        // Horário sempre no fuso institucional, independente do timezone da aplicação
        // (UTC — ver config/app.php): quem lê é gente na sede da instituição. O formato e o
        // fuso vêm de App\Support\InstitutionalTime, o mesmo que monta o "Recebido em" do
        // painel — um e-mail que diz uma hora e uma tela que diz outra é o pior dos dois.
        Mail::to($recipient)->queue(new FormSubmissionReceived($type, (string) InstitutionalTime::label(now()), $adminUrl));
    }
}
