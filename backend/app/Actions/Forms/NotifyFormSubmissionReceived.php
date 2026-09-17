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

        Mail::to($recipient)->queue(new FormSubmissionReceived($type, now()->format('d/m/Y H:i'), $adminUrl));
    }
}
