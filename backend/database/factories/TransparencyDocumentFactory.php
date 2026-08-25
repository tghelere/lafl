<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransparencyDocumentType;
use App\Models\TransparencyDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransparencyDocument>
 */
class TransparencyDocumentFactory extends Factory
{
    protected $model = TransparencyDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'year' => fake()->numberBetween(2015, 2026),
            'type' => fake()->randomElement(TransparencyDocumentType::cases()),
            'file_path' => 'transparency-documents/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(1_000, 500_000),
            'download_count' => 0,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now(),
        ]);
    }
}
