<?php

declare(strict_types=1);

namespace App\Actions\Forms\Data;

final readonly class VolunteerApplicationData
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $availability,
        public string $interestArea,
        public ?string $message,
        public string $ip,
    ) {}
}
