<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PickupRequest;
use App\Support\FieldMasking;
use App\Support\InstitutionalTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Endereço nunca aparece na listagem, nem mascarado — é o dado mais sensível desta fase (ver
 * docs/dominio.md). Só no detalhe, que é auditado.
 *
 * @mixin PickupRequest
 */
final class PickupRequestListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'donor_name' => FieldMasking::firstName($this->donor_name),
            'phone' => FieldMasking::lastDigits($this->phone),
            'items_description' => $this->items_description,
            'availability_window' => $this->availability_window,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'is_read' => $this->isRead(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_label' => InstitutionalTime::label($this->created_at),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
