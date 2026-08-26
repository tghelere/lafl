<?php

declare(strict_types=1);

namespace App\Actions\Forms\Data;

use App\Enums\ChildAgeRange;
use App\Enums\DesiredPeriod;

final readonly class EnrollmentInterestData
{
    public function __construct(
        public string $guardianName,
        public string $phone,
        public string $email,
        public ChildAgeRange $childAgeRange,
        public DesiredPeriod $desiredPeriod,
        public ?string $message,
        public string $ip,
    ) {}
}
