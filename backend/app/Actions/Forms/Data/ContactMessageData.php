<?php

declare(strict_types=1);

namespace App\Actions\Forms\Data;

final readonly class ContactMessageData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $subject,
        public string $message,
        public string $ip,
    ) {}
}
