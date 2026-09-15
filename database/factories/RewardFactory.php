<?php

namespace Database\Factories;

use App\Enums\RewardStatus;
use App\Enums\RewardType;
use App\Models\Customer;
use App\Models\CustomerCardProgress;
use App\Models\Merchant;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    /**
     * Define the model's default state: a completed card waiting to be collected.
     *
     * The customer, merchant and card are copied from the progress record.
     * Without a progress record (birthday gifts) a new customer and merchant
     * are created unless `for()` supplies them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_card_progress_id' => CustomerCardProgress::factory(),
            'customer_id' => fn (array $attributes) => $this->progressFrom($attributes)?->customer_id ?? Customer::factory(),
            'merchant_id' => fn (array $attributes) => $this->progressFrom($attributes)?->merchant_id ?? Merchant::factory(),
            'loyalty_card_id' => fn (array $attributes) => $this->progressFrom($attributes)?->loyalty_card_id,
            'type' => RewardType::CardCompletion,
            'description' => fake()->sentence(3),
            'status' => RewardStatus::Ready,
            'period_key' => null,
            'earned_at' => now(),
        ];
    }

    public function redeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RewardStatus::Redeemed,
            'redeemed_at' => now(),
        ]);
    }

    /**
     * A birthday gift for the current year, not tied to any card.
     */
    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_card_progress_id' => null,
            'loyalty_card_id' => null,
            'type' => RewardType::Birthday,
            'period_key' => (string) now()->year,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function progressFrom(array $attributes): ?CustomerCardProgress
    {
        if (blank($attributes['customer_card_progress_id'] ?? null)) {
            return null;
        }

        return CustomerCardProgress::find($attributes['customer_card_progress_id']);
    }
}
