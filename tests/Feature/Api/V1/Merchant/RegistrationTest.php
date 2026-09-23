<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\MerchantStatus;
use App\Enums\SubscriptionPeriodType;
use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\TrialEmailHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three registration screens of the merchant app.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_merchant_1';

    private const EMAIL = 'shop@example.com';

    public function test_me_sends_a_new_clerk_user_to_the_business_step(): void
    {
        $response = $this->withToken($this->merchantToken())->getJson('/api/v1/merchant/auth/me');

        $response->assertOk()
            ->assertJsonPath('registered', false)
            ->assertJsonPath('registration_step', 'business')
            ->assertJsonPath('data', null);
    }

    public function test_the_business_step_creates_the_shop_and_asks_for_a_package_next(): void
    {
        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $this->businessPayload());

        $response->assertCreated()
            ->assertJsonPath('registration_step', 'package')
            ->assertJsonPath('data.business_name', 'كافيه الياسمين')
            ->assertJsonPath('data.phone', '+963933111222')
            ->assertJsonPath('data.status', null);

        $this->assertDatabaseHas('merchants', [
            'clerk_user_id' => self::CLERK_USER,
            'email' => self::EMAIL,
            'phone' => '+963933111222',
        ]);
    }

    public function test_me_records_the_visit_and_follows_a_changed_clerk_email(): void
    {
        $this->freezeTime();
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER, 'email' => 'old@example.com']);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER, ['email' => 'New@Example.com']))
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertOk()->assertJsonPath('data.email', 'new@example.com');

        $merchant->refresh();
        $this->assertSame('new@example.com', $merchant->email);
        $this->assertSame(now()->toDateTimeString(), $merchant->last_login_at->toDateTimeString());
    }

    public function test_the_business_step_rejects_a_phone_another_shop_uses(): void
    {
        Merchant::factory()->create(['phone' => '+963933111222']);

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $this->businessPayload());

        $response->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_the_business_step_rejects_an_unknown_business_type(): void
    {
        $payload = $this->businessPayload();
        $payload['business_type_id'] = 9999;

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('business_type_id');
    }

    public function test_registering_a_second_shop_for_the_same_clerk_user_returns_409(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $this->businessPayload());

        $response->assertConflict();

        $this->assertDatabaseCount('merchants', 1);
    }

    public function test_choosing_a_package_starts_the_trial_immediately(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        Setting::write('trial_days', 14);
        $merchant = Merchant::factory()->awaitingPackage()->create(['clerk_user_id' => self::CLERK_USER]);
        $package = Package::factory()->withPrices()->create();

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/package', ['package_id' => $package->id]);

        $response->assertOk()
            ->assertJsonPath('registration_step', 'pin')
            ->assertJsonPath('trial_granted', true)
            ->assertJsonPath('data.status', 'TRIAL')
            ->assertJsonPath('data.subscription.type', 'trial');

        $this->assertSame(MerchantStatus::Trial, $merchant->fresh()->status);

        $period = $merchant->subscriptionPeriods()->sole();
        $this->assertSame(SubscriptionPeriodType::Trial, $period->type);
        $this->assertSame('2026-10-07 12:00:00', $period->ends_at->toDateTimeString());
        $this->assertNull($period->grace_ends_at);

        // The fingerprint is what stops a second trial later.
        $this->assertTrue(TrialEmailHash::alreadyUsed(self::EMAIL));
    }

    public function test_a_second_trial_for_the_same_email_is_refused_and_the_shop_starts_expired(): void
    {
        TrialEmailHash::query()->create(['email_hash' => TrialEmailHash::fingerprint(self::EMAIL)]);
        $merchant = Merchant::factory()->awaitingPackage()->create(['clerk_user_id' => self::CLERK_USER]);
        $package = Package::factory()->withPrices()->create();

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/package', ['package_id' => $package->id]);

        $response->assertOk()
            ->assertJsonPath('trial_granted', false)
            ->assertJsonPath('data.status', 'EXPIRED');

        $this->assertSame(0, $merchant->subscriptionPeriods()->count());
    }

    public function test_choosing_a_package_twice_returns_409(): void
    {
        Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);
        $package = Package::factory()->create();

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/package', ['package_id' => $package->id]);

        $response->assertConflict();
    }

    public function test_setting_the_pin_finishes_registration(): void
    {
        $merchant = Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/registration/pin', [
            'pin' => '4321',
            'pin_confirmation' => '4321',
        ]);

        $response->assertOk()
            ->assertJsonPath('registration_step', 'done')
            ->assertJsonPath('data.has_pin', true);

        $this->assertNotNull($merchant->fresh()->pin_hash);
    }

    public function test_setting_a_pin_before_choosing_a_package_returns_409(): void
    {
        Merchant::factory()->awaitingPackage()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/registration/pin', [
            'pin' => '4321',
            'pin_confirmation' => '4321',
        ]);

        $response->assertConflict();
    }

    public function test_the_pin_unlocks_the_protected_tabs(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/pin/verify', ['pin' => '1234'])
            ->assertOk();
    }

    public function test_a_wrong_pin_is_refused(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/pin/verify', ['pin' => '9999']);

        $response->assertUnprocessable()->assertJsonValidationErrors('pin');
    }

    public function test_protected_routes_refuse_a_half_registered_merchant(): void
    {
        Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/pin/verify', ['pin' => '1234']);

        $response->assertForbidden()
            ->assertJsonPath('code', 'registration_incomplete')
            ->assertJsonPath('registration_step', 'pin');
    }

    public function test_an_outdated_app_version_is_refused_with_an_update_code(): void
    {
        Setting::write('merchant_min_app_version', '2.0.0');
        Setting::write('merchant_app_download_url', 'https://wafa.example/app.apk');

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-App-Version', '1.4.0')
            ->getJson('/api/v1/merchant/auth/me');

        $response->assertStatus(426)
            ->assertJsonPath('code', 'app_update_required')
            ->assertJsonPath('download_url', 'https://wafa.example/app.apk');
    }

    public function test_a_current_app_version_passes(): void
    {
        Setting::write('merchant_min_app_version', '2.0.0');

        $this->withToken($this->merchantToken())
            ->withHeader('X-App-Version', '2.1.0')
            ->getJson('/api/v1/merchant/auth/me')
            ->assertOk();
    }

    private function merchantToken(): string
    {
        return $this->clerkToken(self::CLERK_USER, ['email' => self::EMAIL]);
    }

    /**
     * @return array<string, mixed>
     */
    private function businessPayload(): array
    {
        return [
            'business_name' => 'كافيه الياسمين',
            'business_type_id' => BusinessType::factory()->create()->id,
            'governorate_id' => Governorate::factory()->create()->id,
            'address' => 'شارع الحمرا',
            'owner_name' => 'أحمد',
            'phone' => '0933 111 222',
        ];
    }
}
