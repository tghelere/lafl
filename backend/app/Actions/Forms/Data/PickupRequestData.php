<?php

declare(strict_types=1);

namespace App\Actions\Forms\Data;

final readonly class PickupRequestData
{
    public function __construct(
        public string $donorName,
        public string $phone,
        public string $address,
        public string $itemsDescription,
        public string $availabilityWindow,
        public string $ip,
    ) {}
}
