<?php

namespace Tests\Feature\Api\V1;

use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lists the apps load before anyone signs in.
 */
class LookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_governorates_are_returned_in_order_without_the_disabled_ones(): void
    {
        Governorate::factory()->create(['name' => 'دمشق', 'sort_order' => 1]);
        Governorate::factory()->create(['name' => 'حلب', 'sort_order' => 2]);
        Governorate::factory()->create(['name' => 'قديمة', 'sort_order' => 3, 'is_active' => false]);

        $response = $this->getJson('/api/v1/lookups/governorates');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'دمشق')
            ->assertJsonPath('data.1.name', 'حلب');
    }

    public function test_business_types_exclude_the_disabled_ones(): void
    {
        BusinessType::factory()->create(['name' => 'كافيه']);
        BusinessType::factory()->create(['name' => 'ملغى', 'is_active' => false]);

        $response = $this->getJson('/api/v1/lookups/business-types');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'كافيه');
    }

    public function test_packages_come_with_their_price_matrix(): void
    {
        Package::factory()->withPrices(10)->create(['name' => 'الأساسية', 'cards_limit' => 1]);

        $response = $this->getJson('/api/v1/lookups/packages');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'الأساسية')
            ->assertJsonPath('data.0.cards_limit', 1)
            ->assertJsonCount(3, 'data.0.prices')
            ->assertJsonPath('data.0.prices.0.duration_months', 1)
            ->assertJsonPath('data.0.prices.0.price_usd', '10.00');
    }

    public function test_policy_returns_the_version_the_consent_screen_must_send_back(): void
    {
        Setting::write('privacy_policy_version', '1.2');
        Setting::write('privacy_policy_url', 'https://wafa.example/privacy');

        $response = $this->getJson('/api/v1/policy');

        $response->assertOk()
            ->assertJsonPath('privacy_policy_version', '1.2')
            ->assertJsonPath('privacy_policy_url', 'https://wafa.example/privacy');
    }
}
