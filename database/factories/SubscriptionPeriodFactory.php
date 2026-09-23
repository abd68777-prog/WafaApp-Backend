<?php

namespace Database\Factories;

use App\Enums\SubscriptionPeriodType;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPeriod>
 */
class SubscriptionPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'package_id' => Package::factory(),
            'type' => SubscriptionPeriodType::Paid,
            'duration_months' => 1,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'grace_ends_at' => now()->addMonth()->addDays(3),
        ];
    }

    /**
     * The free trial: no duration in months and no grace days after it.
     */
    public function trial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => SubscriptionPeriodType::Trial,
            'duration_months' => null,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
            'grace_ends_at' => null,
        ]);
    }
}
