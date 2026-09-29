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
 * The three registration screens of the merchant app (contract §5.2).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_merchant_1';

    private const EMAIL = 'shop@example.com';

    public function test_the_business_step_creates_the_shop_and_asks_for_a_package_next(): void
    {
        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $this->businessPayload());

        $response->assertCreated()
            ->assertJsonPath('data.registration_step', 'package')
            ->assertJsonPath('data.merchant.business_name', 'كافيه الياسمين')
            ->assertJsonPath('data.merchant.phone', '+963933111222')
            ->assertJsonPath('data.subscription', null);

        $this->assertDatabaseHas('merchants', [
            'clerk_user_id' => self::CLERK_USER,
            'email' => self::EMAIL,
            'phone' => '+963933111222',
        ]);
    }

    public function test_the_business_step_rejects_a_phone_another_shop_uses(): void
    {
        Merchant::factory()->create(['phone' => '+963933111222']);

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $this->businessPayload());

        $response->assertUnprocessable()->assertJsonPath('error.details.fields.phone', ['taken']);
    }

    public function test_the_business_step_rejects_an_unknown_business_type(): void
    {
        $payload = $this->businessPayload();
        $payload['business_type_id'] = 9999;

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $payload);

        $response->assertUnprocessable()->assertJsonPath('error.details.fields.business_type_id', ['exists']);
    }

    public function test_a_step_out_of_turn_names_the_step_to_open(): void
    {
        Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);

        $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/business', $this->businessPayload())
            ->assertConflict()
            ->assertJsonPath('error.code', 'REGISTRATION_STEP_MISMATCH')
            ->assertJsonPath('error.details.registration_step', 'pin');

        $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/package', ['package_id' => Package::factory()->create()->id])
            ->assertConflict()
            ->assertJsonPath('error.details.registration_step', 'pin');

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
            ->assertJsonPath('data.registration_step', 'pin')
            ->assertJsonPath('data.trial_granted', true)
            ->assertJsonPath('data.subscription.status', 'TRIAL')
            ->assertJsonPath('data.subscription.current_period.type', 'trial')
            ->assertJsonPath('data.subscription.current_period.ends_at', '2026-10-07T12:00:00Z');

        $this->assertSame(MerchantStatus::Trial, $merchant->fresh()->status);

        $period = $merchant->subscriptionPeriods()->sole();
        $this->assertSame(SubscriptionPeriodType::Trial, $period->type);
        $this->assertNull($period->grace_ends_at);

        // The fingerprint is what stops a second trial later.
        $this->assertTrue(TrialEmailHash::alreadyUsed(self::EMAIL));
    }

    public function test_a_second_trial_for_the_same_email_is_refused_and_the_shop_starts_expired(): void
    {
        TrialEmailHash::query()->create(['email_hash' => TrialEmailHash::fingerprint(self::EMAIL)]);
        $merchant = Merchant::factory()->awaitingPackage()->create(['clerk_user_id' => self::CLERK_USER, 'email' => self::EMAIL]);
        $package = Package::factory()->withPrices()->create();

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/registration/package', ['package_id' => $package->id]);

        $response->assertOk()
            ->assertJsonPath('data.trial_granted', false)
            ->assertJsonPath('data.subscription.status', 'EXPIRED')
            ->assertJsonPath('data.subscription.trial_used', true);

        $this->assertSame(0, $merchant->subscriptionPeriods()->count());
    }

    public function test_setting_the_pin_finishes_registration_and_unlocks_the_tabs_at_once(): void
    {
        $merchant = Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/registration/pin', [
            'pin' => '4321',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.me.registration_step', 'done')
            ->assertJsonStructure(['data' => ['pin' => ['pin_token', 'expires_at']]]);

        $this->assertNotNull($merchant->fresh()->pin_hash);
    }

    public function test_a_pin_must_be_four_to_six_digits(): void
    {
        Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/registration/pin', [
            'pin' => '12a',
        ]);

        $response->assertUnprocessable()->assertJsonPath('error.details.fields.pin', ['format']);
    }

    public function test_routes_past_registration_refuse_a_half_registered_merchant(): void
    {
        Merchant::factory()->awaitingPin()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/pin/unlock', ['pin' => '1234']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'REGISTRATION_INCOMPLETE')
            ->assertJsonPath('error.details.registration_step', 'pin');
    }

    public function test_an_outdated_app_version_is_refused_with_an_update_code(): void
    {
        Setting::write('merchant_min_app_version', '2.0.0');
        Setting::write('merchant_app_download_url', 'https://wafa.example/app.apk');

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-App-Version', '1.4.0')
            ->getJson('/api/v1/merchant/me');

        $response->assertStatus(426)
            ->assertJsonPath('error.code', 'APP_VERSION_UNSUPPORTED')
            ->assertJsonPath('error.details', [
                'min_version' => '2.0.0',
                'download_url' => 'https://wafa.example/app.apk',
            ]);
    }

    public function test_a_current_app_version_passes(): void
    {
        Setting::write('merchant_min_app_version', '2.0.0');

        $this->withToken($this->merchantToken())
            ->withHeader('X-App-Version', '2.1.0')
            ->getJson('/api/v1/merchant/me')
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
