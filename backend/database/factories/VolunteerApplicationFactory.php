<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VolunteerApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerApplication>
 */
class VolunteerApplicationFactory extends Factory
{
    protected $model = VolunteerApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('(43) 9####-####'),
            'email' => fake()->safeEmail(),
            'availability' => fake()->randomElement(['manhãs', 'tardes', 'fins de semana']),
            'interest_area' => fake()->randomElement(['bazar', 'contraturno', 'eventos', 'administrativo']),
            'message' => fake()->optional()->sentence(),
            'consent_terms_version' => '2026-08-25',
            'consented_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
