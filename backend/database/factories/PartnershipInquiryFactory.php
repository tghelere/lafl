<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PartnershipSupportType;
use App\Models\PartnershipInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnershipInquiry>
 */
class PartnershipInquiryFactory extends Factory
{
    protected $model = PartnershipInquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'tax_id' => fake()->numerify('##############'),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('(43) 9####-####'),
            'email' => fake()->companyEmail(),
            'support_type' => fake()->randomElement(PartnershipSupportType::cases()),
            'message' => fake()->optional()->sentence(),
            'consent_terms_version' => '2026-08-25',
            'consented_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
