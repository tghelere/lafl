<?php

declare(strict_types=1);

namespace App\Actions\Media\Data;

final readonly class MediaDetailsData
{
    public function __construct(
        public string $alt,
        public ?string $caption,
        public bool $depictsAssistedMinor,
    ) {}
}
