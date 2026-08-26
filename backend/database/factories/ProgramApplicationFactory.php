<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProgramApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramApplication>
 */
class ProgramApplicationFactory extends Factory
{
    protected $model = ProgramApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_name' => fake()->name(),
            'phone' => fake()->numerify('(43) 9####-####'),
            'email' => fake()->safeEmail(),
            'teen_age' => fake()->numberBetween(12, 17),
            'school' => fake()->optional()->company(),
            'message' => fake()->optional()->sentence(),
            'consent_terms_version' => '2026-08-25',
            'consented_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
