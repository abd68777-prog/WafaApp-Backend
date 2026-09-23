<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Icon;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'icon_id' => Icon::factory(),
            'name' => fake()->words(2, true),
            'stamps_required' => fake()->numberBetween(3, 10),
            'reward_description' => fake()->sentence(3),
            'terms' => null,
            'status' => CardStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CardStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
