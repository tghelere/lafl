<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Actions\Forms\Data\VolunteerApplicationData;
use App\Models\VolunteerApplication;
use App\Services\BlindIndexService;

final class CreateVolunteerApplication
{
    public function handle(VolunteerApplicationData $data): VolunteerApplication
    {
        $application = new VolunteerApplication([
            'name' => $data->name,
            'phone' => $data->phone,
            'email' => $data->email,
            'availability' => $data->availability,
            'interest_area' => $data->interestArea,
            'message' => $data->message,
        ]);

        $application->consent_terms_version = config('forms.consent_terms_version');
        $application->consented_at = now();
        $application->ip_hash = app(BlindIndexService::class)->hash($data->ip);

        $application->save();

        return $application;
    }
}
