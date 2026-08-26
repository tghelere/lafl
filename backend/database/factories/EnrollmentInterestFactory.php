<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChildAgeRange;
use App\Enums\DesiredPeriod;
use App\Models\EnrollmentInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentInterest>
 */
class EnrollmentInterestFactory extends Factory
{
    protected $model = EnrollmentInterest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_name' => fake()->name(),
            'phone' => fake()->numerify('(43) 9####-####'),
            'email' => fake()->safeEmail(),
            'child_age_range' => fake()->randomElement(ChildAgeRange::cases()),
            'desired_period' => fake()->randomElement(DesiredPeriod::cases()),
            'message' => fake()->optional()->sentence(),
            'consent_terms_version' => '2026-08-25',
            'consented_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
