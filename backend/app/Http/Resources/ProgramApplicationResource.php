<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProgramApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProgramApplication
 */
final class ProgramApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'guardian_name' => $this->guardian_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'teen_age' => $this->teen_age,
            'school' => $this->school,
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
