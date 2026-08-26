<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Actions\Forms\Data\PickupRequestData;
use App\Models\PickupRequest;
use App\Services\BlindIndexService;

final class CreatePickupRequest
{
    public function handle(PickupRequestData $data): PickupRequest
    {
        $request = new PickupRequest([
            'donor_name' => $data->donorName,
            'phone' => $data->phone,
            'address' => $data->address,
            'items_description' => $data->itemsDescription,
            'availability_window' => $data->availabilityWindow,
        ]);

        $request->consent_terms_version = config('forms.consent_terms_version');
        $request->consented_at = now();
        $request->ip_hash = app(BlindIndexService::class)->hash($data->ip);

        $request->save();

        return $request;
    }
}
