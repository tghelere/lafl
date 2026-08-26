<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Actions\Forms\Data\ProgramApplicationData;
use App\Models\ProgramApplication;
use App\Services\BlindIndexService;

final class CreateProgramApplication
{
    public function handle(ProgramApplicationData $data): ProgramApplication
    {
        $application = new ProgramApplication([
            'guardian_name' => $data->guardianName,
            'phone' => $data->phone,
            'email' => $data->email,
            'teen_age' => $data->teenAge,
            'school' => $data->school,
            'message' => $data->message,
        ]);

        $application->consent_terms_version = config('forms.consent_terms_version');
        $application->consented_at = now();
        $application->ip_hash = app(BlindIndexService::class)->hash($data->ip);

        $application->save();

        return $application;
    }
}
