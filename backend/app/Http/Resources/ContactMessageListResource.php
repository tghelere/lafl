<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContactMessage;
use App\Support\FieldMasking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactMessage
 */
final class ContactMessageListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => FieldMasking::firstName($this->name),
            'email' => FieldMasking::email($this->email),
            'subject' => $this->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
