<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\OtpCode;
use App\Models\Setting;
use App\Models\Stamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Signing in with the phone number and a WhatsApp code (contract §4.1).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+963947123456';

    /** The code every OtpCode factory row hashes by default. */
    private const CODE = '123456';

    private const POLICY_VERSION = '1.2';

    public function test_requesting_a_code_sends_it_through_lightotp_and_stores_only_its_hash(): void
    {
        config(['otp.driver' => 'lightotp', 'services.lightotp.key' => 'test-key', 'services.lightotp.base_url' => 'https://api.lightotp.com']);
        Http::fake(['api.lightotp.com/*' => Http::response(['id' => 'a1b2', 'messageStatus' => 'Sent'])]);

        $response = $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::PHONE]);

        $response->assertAccepted()->assertExactJson([
            'data' => ['expires_in_seconds' => 300, 'resend_after_seconds' => 60],
        ]);

        $otpCode = OtpCode::query()->sole();
        Http::assertSent(fn (Request $request): bool => $request['toPhoneE164'] === self::PHONE
            && Hash::check((string) $request['otpCode'], $otpCode->code_hash));
    }

    public function test_a_lightotp_cooldown_answers_resend_too_soon_with_its_wait_and_stores_no_code(): void
    {
        config(['otp.driver' => 'lightotp', 'services.lightotp.key' => 'test-key', 'services.lightotp.base_url' => 'https://api.lightotp.com']);
        Http::preventStrayRequests();
        Http::fake(['api.lightotp.com/SendMessage' => Http::response([
            'errorMessage' => "You can't send another message to the same number yet. Please wait 00:02:00 and try again.",
        ], 400)]);

        $response = $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::PHONE]);

        $response->assertTooManyRequests()
            ->assertJsonPath('error.code', 'OTP_RESEND_TOO_SOON')
            ->assertJsonPath('error.details.retry_after_seconds', 120)
            ->assertHeader('Retry-After', '120');

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_requesting_a_code_normalizes_a_local_phone_number(): void
    {
        $this->postJson('/api/v1/customer/auth/otp', ['phone' => '0947 123 456'])->assertAccepted();

        $this->assertDatabaseHas('otp_codes', ['phone' => self::PHONE]);
    }

    public function test_requesting_a_second_code_before_the_cooldown_answers_resend_too_soon(): void
    {
        $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::PHONE])->assertAccepted();

        $response = $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::PHONE]);

        $response->assertTooManyRequests()
            ->assertJsonPath('error.code', 'OTP_RESEND_TOO_SOON')
            ->assertJsonStructure(['error' => ['details' => ['retry_after_seconds']]]);
    }

    public function test_verifying_a_new_number_creates_the_account_with_its_qr_identity_and_consent(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->verify();

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.needs_profile', true)
            ->assertJsonPath('data.claimed_stamps', [])
            ->assertJsonPath('data.customer.phone', self::PHONE)
            ->assertJsonPath('data.customer.profile_complete', false)
            ->assertJsonPath('data.customer.consented_policy_version', self::POLICY_VERSION)
            // Read back from the database, so column defaults are real values.
            ->assertJsonPath('data.customer.campaigns_muted', false);

        $customer = Customer::query()->sole();
        $this->assertNotNull($customer->registered_at);
        $this->assertSame(12, strlen($customer->qr_id));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $customer->qr_secret);
        $this->assertNotNull($otpCode->fresh()->consumed_at);
        $this->assertSame(['customer'], $customer->tokens()->sole()->abilities);
    }

    public function test_verifying_with_an_outdated_policy_version_is_refused_before_the_code_is_used(): void
    {
        Setting::write('privacy_policy_version', '1.3');
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->verify(['policy_version' => '1.2']);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'POLICY_VERSION_OUTDATED')
            ->assertJsonPath('error.details.current_version', '1.3');

        $this->assertDatabaseCount('customers', 0);
        $this->assertNull($otpCode->fresh()->consumed_at);
    }

    public function test_a_phone_only_customer_keeps_their_stamps_and_is_told_about_them(): void
    {
        $customer = Customer::factory()->pending()->create(['phone' => self::PHONE]);
        $card = Card::factory()->create(['name' => 'بطاقة القهوة']);
        $cycle = CardCycle::factory()->create([
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
            'stamps_count' => 4,
        ]);
        Stamp::factory()->count(4)->create([
            'card_cycle_id' => $cycle->id,
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
        ]);
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->verify();

        $response->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.needs_profile', true)
            ->assertJsonPath('data.claimed_stamps.0.card.name', 'بطاقة القهوة')
            ->assertJsonPath('data.claimed_stamps.0.merchant.id', $card->merchant_id)
            ->assertJsonPath('data.claimed_stamps.0.stamps_count', 4)
            ->assertJsonPath('data.claimed_stamps.0.status', 'COLLECTING');

        // The same row, filled in — nothing was moved or recreated.
        $this->assertDatabaseCount('customers', 1);
        $this->assertNotNull($customer->fresh()->registered_at);
        $this->assertNotNull($customer->fresh()->qr_id);
        $this->assertSame(4, $cycle->fresh()->stamps_count);
    }

    public function test_an_existing_customer_signs_in_and_keeps_their_qr_identity(): void
    {
        $customer = Customer::factory()->create(['phone' => self::PHONE]);
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->verify();

        $response->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.needs_profile', false)
            ->assertJsonPath('data.claimed_stamps', []);

        $this->assertSame($customer->qr_id, $customer->fresh()->qr_id);
        $this->assertSame($customer->qr_secret, $customer->fresh()->qr_secret);
    }

    public function test_a_wrong_code_counts_the_attempt_and_says_how_many_remain(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->verify(['code' => '999999']);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'OTP_INVALID')
            ->assertJsonPath('error.details.attempts_remaining', 4);

        $this->assertSame(1, $otpCode->fresh()->attempts);
    }

    public function test_a_code_is_dead_after_five_wrong_attempts(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE, 'attempts' => 5]);

        $this->verify()->assertUnprocessable()->assertJsonPath('error.code', 'OTP_ATTEMPTS_EXCEEDED');
    }

    public function test_an_expired_or_missing_code_answers_otp_expired(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE, 'expires_at' => now()->subSecond()]);

        $this->verify()->assertUnprocessable()->assertJsonPath('error.code', 'OTP_EXPIRED');
        $this->verify(['phone' => '+963947000000'])->assertUnprocessable()->assertJsonPath('error.code', 'OTP_EXPIRED');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $customer = Customer::factory()->create();
        $keptToken = $customer->createToken('tablet', ['customer'])->plainTextToken;
        $revokedToken = $customer->createToken('phone', ['customer'])->plainTextToken;

        $this->withToken($revokedToken)->postJson('/api/v1/customer/auth/logout')->assertNoContent();

        $this->assertSame(1, $customer->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($keptToken)->getJson('/api/v1/customer/me')->assertOk();
    }

    public function test_a_token_without_the_customer_ability_is_forbidden(): void
    {
        $token = Customer::factory()->create()->createToken('phone', ['other'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/customer/me');

        $response->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function verify(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/customer/auth/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'policy_version' => self::POLICY_VERSION,
            ...$overrides,
        ]);
    }
}
