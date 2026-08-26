<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Actions\Forms\Data\EnrollmentInterestData;
use App\Enums\FormSubmissionType;
use App\Models\EnrollmentInterest;
use App\Services\BlindIndexService;

final class CreateEnrollmentInterest
{
    public function __construct(private readonly NotifyFormSubmissionReceived $notify) {}

    public function handle(EnrollmentInterestData $data): EnrollmentInterest
    {
        $interest = new EnrollmentInterest([
            'guardian_name' => $data->guardianName,
            'phone' => $data->phone,
            'email' => $data->email,
            'child_age_range' => $data->childAgeRange,
            'desired_period' => $data->desiredPeriod,
            'message' => $data->message,
        ]);

        // Consentimento gravado e IP nunca em texto puro (ver docs/protecao-de-dados.md e
        // App\Services\BlindIndexService) — repetido em cada uma das seis Actions de
        // criação, três linhas não justificam uma trait própria só para isso.
        $interest->consent_terms_version = config('forms.consent_terms_version');
        $interest->consented_at = now();
        $interest->ip_hash = app(BlindIndexService::class)->hash($data->ip);

        $interest->save();

        $this->notify->handle(FormSubmissionType::EnrollmentInterest, $interest->uuid);

        return $interest;
    }
}
