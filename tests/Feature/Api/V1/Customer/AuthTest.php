<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerCardProgress;
use App\Models\OtpCode;
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

    public function test_requesting_a_code_sends_it_through_lightotp_and_stores_only_its_hash(): void
    {
        $this->useLightOtpDriver();
        Http::fake(['api.lightotp.com/*' => Http::response(['id' => 'a1b2', 'messageStatus' => 'Sent'])]);

        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertOk()
            ->assertJsonPath('expires_in', 300)
            ->assertJsonPath('resend_after', 60);
        $this->assertArrayNotHasKey('code', $response->json());

        $otpCode = OtpCode::query()->sole();
        $this->assertSame(self::PHONE, $otpCode->phone);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.lightotp.com/SendMessage'
            && $request->hasHeader('X-Api-Key', 'test-key')
            && $request['toPhoneE164'] === self::PHONE
            && preg_match('/^\d{6}$/', (string) $request['otpCode']) === 1
            // The delivered code is the one we stored a hash of, and nothing else.
            && Hash::check((string) $request['otpCode'], $otpCode->code_hash));
    }

    public function test_requesting_a_code_normalizes_a_local_phone_number(): void
    {
        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => '0947 123 456']);

        $response->assertOk();

        $this->assertDatabaseHas('otp_codes', ['phone' => self::PHONE]);
    }

    public function test_requesting_a_code_for_an_invalid_phone_returns_422(): void
    {
        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => '12345']);

        $response->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_requesting_a_second_code_before_the_cooldown_returns_429(): void
    {
        $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE])->assertOk();

        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertTooManyRequests()->assertJsonStructure(['message', 'retry_after']);
        $response->assertHeader('Retry-After');

        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_a_provider_failure_returns_503_and_stores_no_code(): void
    {
        $this->useLightOtpDriver();
        Http::fake(['api.lightotp.com/*' => Http::response(['errorMessage' => 'InsufficientBalance'], 400)]);

        $response = $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::PHONE]);

        $response->assertStatus(503);
        $this->assertStringNotContainsString('InsufficientBalance', (string) $response->json('message'));

        // Rolled back, so the customer is not stuck behind a cooldown for a
        // code that was never delivered.
        $this->assertDatabaseCount('otp_codes', 0);
    }

    public function test_verifying_a_code_creates_the_customer_and_returns_a_token(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.phone', self::PHONE)
            ->assertJsonPath('data.name', 'سارة')
            ->assertJsonPath('is_new_customer', true)
            ->assertJsonStructure(['data' => ['id', 'qr_token', 'status'], 'token']);

        $customer = Customer::query()->sole();
        $this->assertSame(CustomerStatus::Active, $customer->status);
        $this->assertNotNull($customer->qr_token);
        $this->assertNotNull($customer->phone_verified_at);

        $this->assertNotNull($otpCode->fresh()->consumed_at);
        $this->assertSame(['customer'], $customer->tokens()->sole()->abilities);
    }

    public function test_verifying_a_new_customer_without_a_name_returns_422_and_keeps_the_code_usable(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('customers', 0);
        $this->assertNull($otpCode->fresh()->consumed_at);

        // The same code still works once the name is supplied.
        $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
        ])->assertOk();
    }

    public function test_verifying_activates_a_pending_customer_and_keeps_their_stamps(): void
    {
        $customer = Customer::factory()->pending()->create(['phone' => self::PHONE]);
        $progress = CustomerCardProgress::factory()->for($customer)->create(['current_stamps' => 3]);
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $customer->id);

        $activated = $customer->fresh();
        $this->assertSame(CustomerStatus::Active, $activated->status);
        $this->assertSame('سارة', $activated->name);
        $this->assertNotNull($activated->qr_token);

        // Same row, so everything collected before the app was installed stays.
        $this->assertDatabaseCount('customers', 1);
        $this->assertSame(3, $progress->fresh()->current_stamps);
    }

    public function test_verifying_a_wrong_code_returns_422_and_counts_the_attempt(): void
    {
        $otpCode = OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '999999',
            'name' => 'سارة',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame(1, $otpCode->fresh()->attempts);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_verifying_after_too_many_attempts_returns_422(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE, 'attempts' => 5]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_verifying_an_expired_code_returns_422(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE, 'expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_verifying_an_already_used_code_returns_422(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE, 'consumed_at' => now()]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
            'name' => 'سارة',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_verifying_a_suspended_customer_returns_403(): void
    {
        Customer::factory()->create(['phone' => self::PHONE, 'status' => CustomerStatus::Suspended]);
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => self::CODE,
        ]);

        $response->assertForbidden();
    }

    public function test_me_returns_401_without_a_token(): void
    {
        $response = $this->getJson('/api/v1/customer/auth/me');

        $response->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
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

        // Separate requests share one container, so the resolved guard has to be
        // dropped for each token to be checked again.
        $this->app['auth']->forgetGuards();
        $this->withToken($keptToken)->getJson('/api/v1/customer/auth/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($revokedToken)->getJson('/api/v1/customer/auth/me')->assertUnauthorized();
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $customer = Customer::factory()->create();
        $customer->createToken('tablet', ['customer']);
        $token = $customer->createToken('phone', ['customer'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/customer/auth/logout-all')->assertOk();

        $this->assertSame(0, $customer->tokens()->count());
    }

    public function test_a_token_without_the_customer_ability_gets_403(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('phone', ['other'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/customer/auth/me');

        $response->assertForbidden()
            ->assertJsonPath('message', 'This token is not allowed to access this resource.');
    }

    private function useLightOtpDriver(): void
    {
        config([
            'otp.driver' => 'lightotp',
            'services.lightotp.key' => 'test-key',
            'services.lightotp.base_url' => 'https://api.lightotp.com',
        ]);
    }
}
