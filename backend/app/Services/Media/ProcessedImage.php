<?php

declare(strict_types=1);

namespace App\Services\Media;

final readonly class ProcessedImage
{
    /**
     * @param  array<int, string>  $derivatives  largura => bytes do webp, em ordem crescente
     */
    public function __construct(
        public string $mime,
        public string $extension,
        public int $width,
        public int $height,
        public string $original,
        public array $derivatives,
    ) {}

    /**
     * @return list<int>
     */
    public function widths(): array
    {
        return array_keys($this->derivatives);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->original);
    }
}
