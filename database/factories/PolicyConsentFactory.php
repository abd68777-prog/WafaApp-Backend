<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PolicyConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyConsent>
 */
class PolicyConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'policy_version' => '1.2',
            'consented_at' => now(),
        ];
    }
}
