<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'max_cards' => 1,
            'price_monthly_usd' => '10.00',
            'price_yearly_usd' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
