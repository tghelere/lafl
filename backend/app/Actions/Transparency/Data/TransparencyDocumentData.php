<?php

declare(strict_types=1);

namespace App\Actions\Transparency\Data;

use App\Enums\TransparencyDocumentType;
use Illuminate\Http\UploadedFile;

final readonly class TransparencyDocumentData
{
    public function __construct(
        public string $title,
        public int $year,
        public TransparencyDocumentType $type,
        public ?UploadedFile $file,
        public bool $publish,
    ) {}
}
