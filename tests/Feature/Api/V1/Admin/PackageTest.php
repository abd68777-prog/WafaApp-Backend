<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Super Admin sets the packages and their prices (requirements §5.5);
 * the app reads them from lookups, never from its own code.
 */
class PackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
        AdminUser::factory()->create(['clerk_user_id' => 'user_reviewer', 'role' => AdminRole::PaymentsReviewer]);
    }

    public function test_a_new_package_and_its_prices_reach_the_merchant_app(): void
    {
        Setting::write('exchange_rate_syp', 13000);

        $response = $this->as('user_owner')->postJson('/api/v1/admin/packages', [
            'name' => 'الذهبية',
            'cards_limit' => 3,
            'weekly_campaigns_limit' => 2,
            'prices' => [
                ['duration_months' => 1, 'price_usd' => 20],
                ['duration_months' => 12, 'price_usd' => 200],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'الذهبية')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.prices', [
                ['duration_months' => 1, 'price_usd' => '20.00'],
                ['duration_months' => 12, 'price_usd' => '200.00'],
            ]);

        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')
            ->assertJsonPath('data.packages.0.prices.0.amount_syp', '260000.00');

        $this->assertSame('package.created', AuditLog::query()->sole()->action);
    }

    public function test_editing_sets_or_withdraws_single_prices_and_is_audited(): void
    {
        $package = Package::factory()->withPrices(10)->create(['name' => 'الأساسية']);

        $this->as('user_owner')->patchJson("/api/v1/admin/packages/{$package->id}", [
            'cards_limit' => 2,
            'prices' => [
                ['duration_months' => 1, 'price_usd' => 12.5],
                ['duration_months' => 12, 'price_usd' => null],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.cards_limit', 2)
            ->assertJsonPath('data.prices', [
                ['duration_months' => 1, 'price_usd' => '12.50'],
                ['duration_months' => 3, 'price_usd' => '27.00'],
            ]);

        $log = AuditLog::query()->sole();
        $this->assertSame('package.updated', $log->action);
        $this->assertSame(1, $log->before['cards_limit']);
        $this->assertSame(['1' => '12.50', '3' => '27.00'], $log->after['prices']);
    }

    public function test_a_deactivated_package_leaves_the_lookups_but_stays_in_the_dashboard(): void
    {
        $package = Package::factory()->withPrices()->create();

        $this->as('user_owner')->patchJson("/api/v1/admin/packages/{$package->id}", ['is_active' => false])->assertOk();

        $this->as('user_owner')->getJson('/api/v1/admin/packages')->assertJsonPath('data.0.is_active', false);
        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')->assertJsonCount(0, 'data.packages');
    }

    public function test_the_fields_are_validated(): void
    {
        $this->as('user_owner')->postJson('/api/v1/admin/packages', [
            'name' => 'x',
            'cards_limit' => 0,
            'weekly_campaigns_limit' => 1,
            'prices' => [['duration_months' => 2, 'price_usd' => 0]],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', [
                'name' => ['min'],
                'cards_limit' => ['min'],
                'prices.0.duration_months' => ['format'],
                'prices.0.price_usd' => ['min'],
            ]);
    }

    public function test_the_payments_reviewer_cannot_touch_prices(): void
    {
        $package = Package::factory()->withPrices()->create();

        $this->as('user_reviewer')->patchJson("/api/v1/admin/packages/{$package->id}", [
            'prices' => [['duration_months' => 1, 'price_usd' => 1]],
        ])->assertForbidden();

        $this->as('user_reviewer')->getJson('/api/v1/admin/packages')->assertForbidden();
    }

    private function as(string $clerkUserId): self
    {
        return $this->withToken($this->clerkToken($clerkUserId));
    }
}
