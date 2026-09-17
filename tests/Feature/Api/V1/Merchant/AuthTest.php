<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\BillingCycle;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_merchant_1';

    public function test_me_reports_a_clerk_user_who_has_not_registered_a_business(): void
    {
        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/v1/merchant/auth/me');

        $response->assertOk()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('data', null);
    }

    public function test_me_returns_the_registered_merchant(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/v1/merchant/auth/me');

        $response->assertOk()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('data.id', $merchant->id)
            ->assertJsonPath('data.package.code', $merchant->package->code);
    }

    public function test_registering_starts_a_trial_and_opens_the_trial_subscription(): void
    {
        $this->travelTo('2026-09-17 12:00:00');
        Package::factory()->create(['code' => 'basic']);
        PlatformSetting::factory()->create(['key' => 'trial_days', 'value' => 14]);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/auth/register', [
            'business_name' => 'كافيه الياسمين',
            'owner_name' => 'أحمد',
            'phone' => '0933 111 222',
            'package_code' => 'basic',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonPath('data.phone', '+963933111222')
            ->assertJsonPath('data.package.code', 'basic')
            ->assertJsonPath('data.trial_ends_at', '2026-10-01T12:00:00+00:00')
            ->assertJsonPath('data.birthday_gift_enabled', false);

        $merchant = Merchant::query()->sole();
        $this->assertSame(self::CLERK_USER, $merchant->clerk_user_id);
        $this->assertSame(MerchantStatus::Trial, $merchant->status);

        $subscription = $merchant->subscriptions()->sole();
        $this->assertSame(BillingCycle::Trial, $subscription->billing_cycle);
        $this->assertSame('0.00', $subscription->price_usd);
        $this->assertSame('2026-10-01 12:00:00', $subscription->ends_at->toDateTimeString());
    }

    public function test_registering_a_second_business_for_the_same_clerk_user_returns_409(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        Package::factory()->create(['code' => 'basic']);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/auth/register', [
            'business_name' => 'فرع تاني',
            'owner_name' => 'أحمد',
            'phone' => '0933 999 888',
            'package_code' => 'basic',
        ]);

        $response->assertConflict();

        $this->assertDatabaseCount('merchants', 1);
    }

    public function test_registering_with_a_phone_another_merchant_uses_returns_422(): void
    {
        Merchant::factory()->create(['phone' => '+963933111222']);
        Package::factory()->create(['code' => 'basic']);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/auth/register', [
            'business_name' => 'كافيه الياسمين',
            'owner_name' => 'أحمد',
            'phone' => '0933111222',
            'package_code' => 'basic',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->assertDatabaseMissing('merchants', ['clerk_user_id' => self::CLERK_USER]);
    }

    public function test_registering_with_an_unknown_package_returns_422(): void
    {
        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/auth/register', [
            'business_name' => 'كافيه الياسمين',
            'owner_name' => 'أحمد',
            'phone' => '0933111222',
            'package_code' => 'gold',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('package_code');
    }

    public function test_merchant_only_routes_refuse_a_clerk_user_without_a_business(): void
    {
        $this->registerMerchantOnlyRoute();

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/test/merchant-only');

        $response->assertForbidden()->assertJsonPath('code', 'merchant_not_registered');
    }

    public function test_merchant_only_routes_refuse_a_suspended_merchant(): void
    {
        $this->registerMerchantOnlyRoute();
        Merchant::factory()->suspended()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/test/merchant-only');

        $response->assertForbidden()->assertJsonPath('code', 'merchant_suspended');
    }

    public function test_merchant_only_routes_refuse_a_rejected_merchant(): void
    {
        $this->registerMerchantOnlyRoute();
        Merchant::factory()->rejected()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/test/merchant-only');

        $response->assertForbidden()->assertJsonPath('code', 'merchant_rejected');
    }

    public function test_merchant_only_routes_resolve_the_merchant_as_the_request_user(): void
    {
        $this->registerMerchantOnlyRoute();
        $merchant = Merchant::factory()->trial()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/test/merchant-only');

        $response->assertOk()->assertJsonPath('merchant_id', $merchant->id);
    }

    /**
     * No production route uses `clerk.merchant` yet, so the tests mount one.
     */
    private function registerMerchantOnlyRoute(): void
    {
        Route::middleware(['api', 'clerk', 'clerk.merchant'])
            ->get('/api/test/merchant-only', fn (Request $request): array => ['merchant_id' => $request->user()->id]);
    }
}
