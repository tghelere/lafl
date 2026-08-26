<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EnrollmentInterest;
use App\Support\FieldMasking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Listagem administrativa — dado pessoal mascarado (ver docs/protecao-de-dados.md). O valor
 * completo só aparece em EnrollmentInterestResource (detalhe), que é auditado.
 *
 * @mixin EnrollmentInterest
 */
final class EnrollmentInterestListResource extends JsonResource
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
            'email' => FieldMasking::email($this->email),
            'child_age_range' => $this->child_age_range->value,
            'child_age_range_label' => $this->child_age_range->label(),
            'desired_period' => $this->desired_period->value,
            'desired_period_label' => $this->desired_period->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
