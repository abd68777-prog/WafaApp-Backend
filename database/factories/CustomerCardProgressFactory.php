<?php

namespace Database\Factories;

use App\Enums\StampSource;
use App\Models\Customer;
use App\Models\CustomerCardProgress;
use App\Models\LoyaltyCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCardProgress>
 */
class CustomerCardProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `merchant_id` is derived from the card so the denormalized copy always
     * matches the card's owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'loyalty_card_id' => LoyaltyCard::factory(),
            'merchant_id' => fn (array $attributes) => LoyaltyCard::find($attributes['loyalty_card_id'])->merchant_id,
            'current_stamps' => 0,
            'total_stamps' => 0,
            'rewards_earned' => 0,
            'enrolled_via' => StampSource::Qr,
            'last_stamped_at' => null,
        ];
    }
}
