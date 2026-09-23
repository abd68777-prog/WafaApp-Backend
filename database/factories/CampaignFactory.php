<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'recipients_count' => 0,
            'sent_at' => now(),
        ];
    }
}
