<?php

declare(strict_types=1);

namespace App\Http\Requests\Forms;

use App\Actions\Forms\Data\PickupRequestData;
use Illuminate\Foundation\Http\FormRequest;

final class StorePickupRequestRequest extends FormRequest
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
            'donor_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'items_description' => ['required', 'string', 'max:2000'],
            'availability_window' => ['required', 'string', 'max:255'],
            'consent' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'donor_name.required' => 'Informe seu nome.',
            'phone.required' => 'Informe um telefone para contato.',
            'address.required' => 'Informe o endereço para a coleta.',
            'items_description.required' => 'Descreva os itens a doar.',
            'availability_window.required' => 'Informe quando você costuma estar disponível.',
            'consent.accepted' => 'É preciso concordar com a política de privacidade para enviar.',
        ];
    }

    public function toDto(): PickupRequestData
    {
        return new PickupRequestData(
            donorName: $this->string('donor_name')->value(),
            phone: $this->string('phone')->value(),
            address: $this->string('address')->value(),
            itemsDescription: $this->string('items_description')->value(),
            availabilityWindow: $this->string('availability_window')->value(),
            ip: (string) $this->ip(),
        );
    }
}
