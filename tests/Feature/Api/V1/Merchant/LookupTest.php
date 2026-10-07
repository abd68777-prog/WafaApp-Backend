<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Icon;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The lists, prices and limits the merchant app's forms are built from
 * (contract MerchantLookups). Open before registration is complete.
 */
class LookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_come_in_order_without_the_disabled_entries(): void
    {
        $damascus = Governorate::factory()->create(['name' => 'دمشق', 'sort_order' => 1]);
        $aleppo = Governorate::factory()->create(['name' => 'حلب', 'sort_order' => 2]);
        Governorate::factory()->create(['name' => 'قديمة', 'sort_order' => 3, 'is_active' => false]);
        BusinessType::factory()->create(['name' => 'ملغى', 'is_active' => false]);
        Icon::factory()->create(['key' => 'coffee-cup', 'name' => 'قهوة']);

        $response = $this->lookups();

        $response->assertOk()
            ->assertJsonPath('data.governorates', [
                ['id' => $damascus->id, 'name' => 'دمشق'],
                ['id' => $aleppo->id, 'name' => 'حلب'],
            ])
            ->assertJsonPath('data.business_types', [])
            ->assertJsonPath('data.icons.0.key', 'coffee-cup');
    }

    public function test_packages_carry_prices_as_decimal_strings_with_the_current_pound_amount(): void
    {
        Setting::write('exchange_rate_syp', 13000);
        Package::factory()->withPrices(10)->create(['name' => 'الأساسية', 'cards_limit' => 1]);

        $response = $this->lookups();

        $response->assertOk()
            ->assertJsonPath('data.packages.0.name', 'الأساسية')
            ->assertJsonPath('data.packages.0.cards_limit', 1)
            ->assertJsonPath('data.packages.0.prices.0', [
                'duration_months' => 1,
                'price_usd' => '10.00',
                'amount_syp' => '130000.00',
            ])
            ->assertJsonPath('data.payment.exchange_rate_syp', '13000.00');
    }

    public function test_input_limits_come_from_the_runtime_settings(): void
    {
        Setting::write('card_stamps_max', 12);
        Setting::write('stamp_interval_minutes', 30);

        $response = $this->lookups();

        $response->assertOk()
            ->assertJsonPath('data.limits.card_stamps_max', 12)
            ->assertJsonPath('data.limits.stamp_interval_minutes', 30)
            ->assertJsonPath('data.trial_days', 14)
            ->assertJsonStructure(['data' => ['links' => ['privacy_policy_url', 'merchant_terms_url']]]);
    }

    private function lookups(): TestResponse
    {
        return $this->withToken($this->clerkToken('user_not_registered_yet'))->getJson('/api/v1/merchant/lookups');
    }
}
