<?php

namespace Database\Factories;

use App\Models\BirthdayGreeting;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BirthdayGreeting>
 */
class BirthdayGreetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'greeted_on' => now()->toDateString(),
        ];
    }
}
