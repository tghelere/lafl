<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Dados sintéticos. A logo é só a linha em `media` (ver App\Database\Factories\MediaFactory).
 *
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'media_id' => Media::factory(),
            'url' => null,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function withUrl(?string $url = null): static
    {
        return $this->state(fn (): array => ['url' => $url ?? 'https://'.fake()->domainName()]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
