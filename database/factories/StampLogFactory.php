<?php

namespace Database\Factories;

use App\Enums\StampSource;
use App\Models\CustomerCardProgress;
use App\Models\StampLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StampLog>
 */
class StampLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The customer, card and merchant are copied from the progress record so
     * the denormalized columns stay consistent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_card_progress_id' => CustomerCardProgress::factory(),
            'customer_id' => fn (array $attributes) => CustomerCardProgress::find($attributes['customer_card_progress_id'])->customer_id,
            'loyalty_card_id' => fn (array $attributes) => CustomerCardProgress::find($attributes['customer_card_progress_id'])->loyalty_card_id,
            'merchant_id' => fn (array $attributes) => CustomerCardProgress::find($attributes['customer_card_progress_id'])->merchant_id,
            'quantity' => 1,
            'source' => StampSource::Qr,
            'client_uuid' => fake()->uuid(),
            'device_id' => null,
            'stamped_at' => now(),
        ];
    }
}
