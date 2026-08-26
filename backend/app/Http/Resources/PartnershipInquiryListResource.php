<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PartnershipInquiry;
use App\Support\FieldMasking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `company_name` não é dado pessoal de pessoa física (ver App\Models\PartnershipInquiry) —
 * único campo de identificação exibido em texto puro na listagem.
 *
 * @mixin PartnershipInquiry
 */
final class PartnershipInquiryListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'company_name' => $this->company_name,
            'tax_id' => FieldMasking::lastDigits($this->tax_id),
            'contact_name' => FieldMasking::firstName($this->contact_name),
            'phone' => FieldMasking::lastDigits($this->phone),
            'email' => FieldMasking::email($this->email),
            'support_type' => $this->support_type->value,
            'support_type_label' => $this->support_type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
