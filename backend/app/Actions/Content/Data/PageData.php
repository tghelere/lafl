<?php

declare(strict_types=1);

namespace App\Actions\Content\Data;

use App\Enums\PageStatus;

final readonly class PageData
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $content,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public PageStatus $status,
    ) {}
}
