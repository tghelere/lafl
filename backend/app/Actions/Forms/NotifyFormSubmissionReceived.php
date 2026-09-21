<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Enums\FormSubmissionType;
use App\Mail\FormSubmissionReceived;
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

        // Horário sempre em America/Sao_Paulo no e-mail, independente do timezone da
        // aplicação (UTC — ver config/app.php): quem lê é gente na sede da instituição.
        Mail::to($recipient)->queue(new FormSubmissionReceived($type, now('America/Sao_Paulo')->format('d/m/Y H:i'), $adminUrl));
    }
}
