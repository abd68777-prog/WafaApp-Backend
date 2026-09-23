<?php

namespace Database\Factories;

use App\Enums\StampMethod;
use App\Models\CardCycle;
use App\Models\Stamp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stamp>
 */
class StampFactory extends Factory
{
    /**
     * Card, customer and merchant are copied from the cycle so the
     * denormalized columns stay consistent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_cycle_id' => CardCycle::factory(),
            'card_id' => fn (array $attributes) => CardCycle::find($attributes['card_cycle_id'])->card_id,
            'customer_id' => fn (array $attributes) => CardCycle::find($attributes['card_cycle_id'])->customer_id,
            'merchant_id' => fn (array $attributes) => CardCycle::find($attributes['card_cycle_id'])->merchant_id,
            'method' => StampMethod::Qr,
            'client_uuid' => fake()->uuid(),
            'stamped_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => now(),
            'cancel_reason' => 'Added to the wrong customer',
        ]);
    }
}
