<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VolunteerApplication;
use App\Support\InstitutionalTime;
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
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),
            'read_at_label' => InstitutionalTime::label($this->read_at),
            'read_by' => $this->whenLoaded('readBy', fn () => $this->readBy?->name),
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
