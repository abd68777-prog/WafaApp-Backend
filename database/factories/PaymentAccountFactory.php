<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The migration already creates one row per method, so tests usually update
 * those; this makes one only for a method whose row was removed.
 *
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'method' => PaymentMethod::ShamCash,
            'is_active' => true,
            'sort_order' => 1,
            'account' => fake()->numerify('SC-########'),
        ];
    }
}
