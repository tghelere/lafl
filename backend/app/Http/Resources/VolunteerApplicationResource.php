<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VolunteerApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VolunteerApplication
 */
final class VolunteerApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'availability' => $this->availability,
            'interest_area' => $this->interest_area,
            'message' => $this->message,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'internal_note' => $this->internal_note,
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'handled_at' => $this->handled_at?->toIso8601String(),
            'consented_at' => $this->consented_at?->toIso8601String(),
            'consent_terms_version' => $this->consent_terms_version,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
