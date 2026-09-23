<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantMute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantMute>
 */
class MerchantMuteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'merchant_id' => Merchant::factory(),
        ];
    }
}
