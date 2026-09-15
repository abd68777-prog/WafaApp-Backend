<?php

namespace Database\Factories;

use App\Enums\CampaignAudience;
use App\Enums\CampaignStatus;
use App\Models\Merchant;
use App\Models\MerchantCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantCampaign>
 */
class MerchantCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'loyalty_card_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => CampaignAudience::All,
            'audience_filter' => null,
            'recipients_count' => 0,
            'status' => CampaignStatus::Draft,
            'sent_at' => null,
        ];
    }
}
