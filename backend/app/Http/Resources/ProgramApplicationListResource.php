<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProgramApplication;
use App\Support\FieldMasking;
use App\Support\InstitutionalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProgramApplication
 */
final class ProgramApplicationListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'guardian_name' => FieldMasking::firstName($this->guardian_name),
            'phone' => FieldMasking::lastDigits($this->phone),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_label' => InstitutionalTime::label($this->created_at),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
