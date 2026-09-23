<?php

namespace Database\Factories;

use App\Enums\CardCycleStatus;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardCycle>
 */
class CardCycleFactory extends Factory
{
    /**
     * The merchant is copied from the card, so the denormalized column always
     * matches the card's owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'customer_id' => Customer::factory(),
            'merchant_id' => fn (array $attributes) => Card::find($attributes['card_id'])->merchant_id,
            'stamps_count' => 0,
            'status' => CardCycleStatus::Collecting,
        ];
    }

    public function rewardReady(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CardCycleStatus::RewardReady,
            'completed_at' => now(),
        ]);
    }

    public function redeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CardCycleStatus::Redeemed,
            'completed_at' => now()->subDay(),
            'redeemed_at' => now(),
        ]);
    }
}
