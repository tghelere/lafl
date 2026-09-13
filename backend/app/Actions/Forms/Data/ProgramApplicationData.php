<?php

declare(strict_types=1);

namespace App\Actions\Forms\Data;

final readonly class ProgramApplicationData
{
    public function __construct(
        public string $guardianName,
        public string $phone,
        public string $ip,
    ) {}
}
