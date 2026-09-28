<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PartnershipInquiry;
use App\Support\InstitutionalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartnershipInquiry
 */
final class PartnershipInquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'support_type' => $this->support_type->value,
            'support_type_label' => $this->support_type->label(),
            'message' => $this->message,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'internal_note' => $this->internal_note,
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'handled_at' => $this->handled_at?->toIso8601String(),
            'consented_at' => $this->consented_at?->toIso8601String(),
            'consent_terms_version' => $this->consent_terms_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_label' => InstitutionalTime::label($this->created_at),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
