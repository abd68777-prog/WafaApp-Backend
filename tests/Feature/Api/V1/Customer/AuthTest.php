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
use Tests\TestCase;

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

        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertOk()->assertJsonPath('expires_in', 300);
        $this->assertArrayNotHasKey('code', $response->json());

        $otpCode = OtpCode::query()->sole();
        Http::assertSent(fn (Request $request): bool => $request['toPhoneE164'] === self::PHONE
            && Hash::check((string) $request['otpCode'], $otpCode->code_hash));
    }

    public function test_a_lightotp_cooldown_returns_429_with_its_wait_and_stores_no_code(): void
    {
        config(['otp.driver' => 'lightotp', 'services.lightotp.key' => 'test-key', 'services.lightotp.base_url' => 'https://api.lightotp.com']);
        Http::preventStrayRequests();
        Http::fake(['api.lightotp.com/SendMessage' => Http::response([
            'errorMessage' => "You can't send another message to the same number yet. Please wait 00:02:00 and try again.",
        ], 400)]);

        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertTooManyRequests()
            ->assertJsonPath('retry_after', 120)
            ->assertHeader('Retry-After', '120');

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_requesting_a_code_normalizes_a_local_phone_number(): void
    {
        $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => '0947 123 456'])->assertOk();

        $this->assertDatabaseHas('otp_codes', ['phone' => self::PHONE]);
    }

    public function test_requesting_a_second_code_before_the_cooldown_returns_429(): void
    {
        $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE])->assertOk();

        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertTooManyRequests()->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_verifying_creates_the_account_and_records_the_policy_consent(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
            'birthdate' => '1998-05-20',
            'policy_version' => self::POLICY_VERSION,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'سارة')
            ->assertJsonPath('is_new_customer', true)
            ->assertJsonPath('stamps_waiting', [])
            // Read back from the database, so values filled by column defaults
            // are real values and not null.
            ->assertJsonPath('data.campaigns_muted', false)
            ->assertJsonStructure(['data' => ['id', 'qr_secret', 'qr_period_seconds'], 'token']);

        $customer = Customer::query()->sole();
        $this->assertNotNull($customer->registered_at);
        $this->assertNotNull($customer->qr_secret);
        $this->assertNotNull($otpCode->fresh()->consumed_at);
        $this->assertSame(['customer'], $customer->tokens()->sole()->abilities);

        $this->assertDatabaseHas('policy_consents', [
            'customer_id' => $customer->id,
            'policy_version' => self::POLICY_VERSION,
        ]);
    }

    public function test_verifying_a_new_account_without_a_profile_returns_422_and_keeps_the_code_usable(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'birthdate', 'policy_version']);

        $this->assertDatabaseCount('customers', 0);
        $this->assertNull($otpCode->fresh()->consumed_at);

        // The same code still works once the profile is supplied.
        $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
            'birthdate' => '1998-05-20',
            'policy_version' => self::POLICY_VERSION,
        ])->assertOk();
    }

    public function test_verifying_rejects_someone_under_thirteen(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'طفل',
            'birthdate' => now()->subYears(12)->toDateString(),
            'policy_version' => self::POLICY_VERSION,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('birthdate');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_verifying_rejects_an_outdated_policy_version(): void
    {
        Setting::write('privacy_policy_version', '1.3');
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
            'birthdate' => '1998-05-20',
            'policy_version' => '1.1',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('policy_version');
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

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
            'birthdate' => '1998-05-20',
            'policy_version' => self::POLICY_VERSION,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('is_new_customer', true)
            ->assertJsonPath('stamps_waiting.0.card', 'بطاقة القهوة')
            ->assertJsonPath('stamps_waiting.0.stamps', 4);

        // The same row, filled in — nothing was moved or recreated.
        $this->assertDatabaseCount('customers', 1);
        $this->assertNotNull($customer->fresh()->registered_at);
        $this->assertSame(4, $cycle->fresh()->stamps_count);
    }

    public function test_an_existing_customer_signs_in_without_sending_a_profile_again(): void
    {
        $customer = Customer::factory()->create(['phone' => self::PHONE, 'name' => 'سارة']);
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('is_new_customer', false);

        $this->assertDatabaseCount('policy_consents', 0);
    }

    public function test_verifying_a_wrong_code_returns_422_and_counts_the_attempt(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '999999',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame(1, $otpCode->fresh()->attempts);
    }

    public function test_me_returns_401_without_a_token(): void
    {
        $this->getJson('/api/v1/customer/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_the_authenticated_customer(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('phone', ['customer'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/customer/auth/me');

        $response->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $customer = Customer::factory()->create();
        $keptToken = $customer->createToken('tablet', ['customer'])->plainTextToken;
        $revokedToken = $customer->createToken('phone', ['customer'])->plainTextToken;

        $this->withToken($revokedToken)->postJson('/api/v1/customer/auth/logout')->assertOk();

        $this->assertSame(1, $customer->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($keptToken)->getJson('/api/v1/customer/auth/me')->assertOk();
    }

    public function test_a_token_without_the_customer_ability_gets_403(): void
    {
        $token = Customer::factory()->create()->createToken('phone', ['other'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/customer/auth/me');

        $response->assertForbidden()
            ->assertJsonPath('message', 'This token is not allowed to access this resource.');
    }
}
