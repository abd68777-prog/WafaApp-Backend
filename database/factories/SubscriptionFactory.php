<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'package_id' => Package::factory(),
            'billing_cycle' => BillingCycle::Monthly,
            'price_usd' => '10.00',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => SubscriptionStatus::Active,
        ];
    }
}
