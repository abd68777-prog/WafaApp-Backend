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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'cards_limit' => 1,
            'weekly_campaigns_limit' => 1,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A package with the usual price matrix attached.
     */
    public function withPrices(float $monthly = 10): static
    {
        return $this->afterCreating(function (Package $package) use ($monthly): void {
            foreach ([1 => $monthly, 3 => $monthly * 3 * 0.9, 12 => $monthly * 12 * 0.8] as $months => $price) {
                $package->prices()->create([
                    'duration_months' => $months,
                    'price_usd' => round($price, 2),
                ]);
            }
        });
    }
}
