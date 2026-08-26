<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VolunteerApplication;
use App\Support\FieldMasking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VolunteerApplication
 */
final class VolunteerApplicationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => FieldMasking::firstName($this->name),
            'phone' => FieldMasking::lastDigits($this->phone),
            'email' => FieldMasking::email($this->email),
            'availability' => $this->availability,
            'interest_area' => $this->interest_area,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
