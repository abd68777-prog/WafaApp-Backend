<?php

namespace Database\Factories;

use App\Models\LoyaltyCard;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyCard>
 */
class LoyaltyCardFactory extends Factory
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
            'name' => fake()->words(2, true),
            'description' => null,
            'stamps_required' => fake()->numberBetween(5, 10),
            'reward_description' => fake()->sentence(3),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
