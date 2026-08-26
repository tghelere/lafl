<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Actions\Forms\Data\ContactMessageData;
use App\Enums\FormSubmissionType;
use App\Models\ContactMessage;
use App\Services\BlindIndexService;

final class CreateContactMessage
{
    public function __construct(private readonly NotifyFormSubmissionReceived $notify) {}

    public function handle(ContactMessageData $data): ContactMessage
    {
        $message = new ContactMessage([
            'name' => $data->name,
            'email' => $data->email,
            'subject' => $data->subject,
            'message' => $data->message,
        ]);

        $message->consent_terms_version = config('forms.consent_terms_version');
        $message->consented_at = now();
        $message->ip_hash = app(BlindIndexService::class)->hash($data->ip);

        $message->save();

        $this->notify->handle(FormSubmissionType::ContactMessage, $message->uuid);

        return $message;
    }
}
