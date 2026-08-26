<?php

declare(strict_types=1);

namespace App\Actions\Forms\Data;

use App\Enums\PartnershipSupportType;

final readonly class PartnershipInquiryData
{
    public function __construct(
        public string $companyName,
        public string $taxId,
        public string $contactName,
        public string $phone,
        public string $email,
        public PartnershipSupportType $supportType,
        public ?string $message,
        public string $ip,
    ) {}
}
