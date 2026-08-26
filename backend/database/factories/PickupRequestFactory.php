<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FormSubmissionStatus;
use App\Models\PickupRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PickupRequest>
 */
class PickupRequestFactory extends Factory
{
    protected $model = PickupRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_name' => fake()->name(),
            'phone' => fake()->numerify('(43) 9####-####'),
            'address' => fake()->address(),
            'items_description' => fake()->sentence(),
            'availability_window' => fake()->randomElement(['terças e quintas à tarde', 'fins de semana', 'qualquer dia pela manhã']),
            'consent_terms_version' => '2026-08-25',
            'consented_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => FormSubmissionStatus::Done]);
    }
}
