<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Actions\Forms\Data\PartnershipInquiryData;
use App\Models\PartnershipInquiry;
use App\Services\BlindIndexService;

final class CreatePartnershipInquiry
{
    public function handle(PartnershipInquiryData $data): PartnershipInquiry
    {
        $inquiry = new PartnershipInquiry([
            'company_name' => $data->companyName,
            'tax_id' => $data->taxId,
            'contact_name' => $data->contactName,
            'phone' => $data->phone,
            'email' => $data->email,
            'support_type' => $data->supportType,
            'message' => $data->message,
        ]);

        $inquiry->consent_terms_version = config('forms.consent_terms_version');
        $inquiry->consented_at = now();
        $inquiry->ip_hash = app(BlindIndexService::class)->hash($data->ip);

        // tax_id_hash é calculado automaticamente pelo HasBlindIndex ao salvar.
        $inquiry->save();

        return $inquiry;
    }
}
