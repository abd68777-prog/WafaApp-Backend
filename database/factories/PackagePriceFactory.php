<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagePrice>
 */
class PackagePriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'duration_months' => 1,
            'price_usd' => '10.00',
        ];
    }
}
