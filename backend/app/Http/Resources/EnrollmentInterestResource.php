<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EnrollmentInterest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalhe administrativo — dado completo, sem máscara. Todo acesso a este Resource é
 * auditado pelo Controller antes de servi-lo (ver
 * App\Http\Controllers\Api\V1\Concerns\LogsSubmissionAccess).
 *
 * @mixin EnrollmentInterest
 */
final class EnrollmentInterestResource extends JsonResource
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
            'child_age_range' => $this->child_age_range->value,
            'child_age_range_label' => $this->child_age_range->label(),
            'desired_period' => $this->desired_period->value,
            'desired_period_label' => $this->desired_period->label(),
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
