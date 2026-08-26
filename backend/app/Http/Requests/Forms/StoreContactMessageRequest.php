<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\ContactMessageData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe um e-mail para resposta.',
            'email.email' => 'Informe um e-mail válido.',
            'subject.required' => 'Informe o assunto da mensagem.',
            'message.required' => 'Escreva sua mensagem.',
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): ContactMessageData
    {
        return new ContactMessageData(
            name: $this->string('name')->value(),
            email: $this->string('email')->value(),
            subject: $this->string('subject')->value(),
            message: $this->string('message')->value(),
            ip: (string) $this->ip(),
        );
    }
}
