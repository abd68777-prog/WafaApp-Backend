<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Models\Merchant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The PIN separates the owner from the cashier on one shared account
 * (contract §5.4): the server refuses the protected tabs without proof of it,
 * locks out guessing, and resetting it takes a fresh Clerk sign-in the cashier
 * cannot pass.
 */
class PinTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_merchant_1';

    private const PROBE = '/api/v1/merchant/pin-probe';

    protected function setUp(): void
    {
        parent::setUp();

        // A stand-in route with the same middleware stack as the PIN tabs.
        Route::middleware(['api', 'app.version', 'clerk', 'clerk.merchant', 'merchant.pin'])
            ->get(self::PROBE, fn () => ['opened' => true]);
    }

    public function test_the_correct_pin_returns_a_token_that_opens_the_protected_tabs(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        Setting::write('pin_unlock_hours', 12);
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/pin/unlock', ['pin' => '1234']);

        $response->assertOk()->assertJsonPath('data.expires_at', '2026-09-24T00:00:00Z');

        $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $response->json('data.pin_token'))
            ->getJson(self::PROBE)
            ->assertOk()
            ->assertJsonPath('opened', true);
    }

    public function test_the_protected_tabs_refuse_a_request_without_the_pin(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->getJson(self::PROBE);

        $response->assertForbidden()->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_an_unlock_token_stops_working_once_it_expires(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        Setting::write('pin_unlock_hours', 12);
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $pinToken = $this->unlock();

        $this->travelTo('2026-09-24 00:00:01');

        $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $pinToken)
            ->getJson(self::PROBE)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_an_unlock_token_opens_only_the_shop_it_was_issued_for(): void
    {
        Merchant::factory()->create(['clerk_user_id' => 'user_other_shop']);
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $otherShopsToken = $this->unlock('user_other_shop');

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $otherShopsToken)
            ->getJson(self::PROBE);

        $response->assertForbidden()->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_a_forged_unlock_token_is_refused(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', base64_encode('{"merchant":1}'))
            ->getJson(self::PROBE);

        $response->assertForbidden()->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_a_wrong_pin_says_how_many_tries_remain_and_five_lock_the_shop_out(): void
    {
        $this->freezeSecond();
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        foreach ([4, 3, 2, 1, 0] as $remaining) {
            $this->withToken($this->merchantToken())
                ->postJson('/api/v1/merchant/pin/unlock', ['pin' => '9999'])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'PIN_INVALID')
                ->assertJsonPath('error.details.attempts_remaining', $remaining);
        }

        // Even the right PIN waits out the lockout.
        $this->withToken($this->merchantToken())
            ->postJson('/api/v1/merchant/pin/unlock', ['pin' => '1234'])
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'PIN_LOCKED')
            ->assertJsonPath('error.details.retry_after_seconds', 900);

        $this->travel(901)->seconds();

        $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/pin/unlock', ['pin' => '1234'])->assertOk();
    }

    public function test_changing_the_pin_needs_the_current_one_and_voids_every_old_unlock_token(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $pinToken = $this->unlock();

        $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $pinToken)
            ->putJson('/api/v1/merchant/pin', ['current_pin' => '0000', 'new_pin' => '5678'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PIN_INVALID');

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $pinToken)
            ->putJson('/api/v1/merchant/pin', ['current_pin' => '1234', 'new_pin' => '5678']);

        $response->assertOk()->assertJsonStructure(['data' => ['pin_token', 'expires_at']]);
        $this->assertTrue(Hash::check('5678', $merchant->fresh()->pin_hash));

        $this->withToken($this->merchantToken())->withHeader('X-Pin-Token', $pinToken)->getJson(self::PROBE)->assertForbidden();
        $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $response->json('data.pin_token'))
            ->getJson(self::PROBE)
            ->assertOk();
    }

    public function test_changing_the_pin_is_itself_a_protected_tab(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $this->withToken($this->merchantToken())
            ->putJson('/api/v1/merchant/pin', ['current_pin' => '1234', 'new_pin' => '5678'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_a_fresh_clerk_sign_in_resets_a_forgotten_pin(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $oldPinToken = $this->unlock();

        $response = $this->withToken($this->merchantToken(['fva' => [2, -1]]))
            ->postJson('/api/v1/merchant/pin/reset', ['new_pin' => '5678']);

        $response->assertOk()->assertJsonStructure(['data' => ['pin_token', 'expires_at']]);
        $this->assertTrue(Hash::check('5678', $merchant->fresh()->pin_hash));
        $this->withToken($this->merchantToken())->withHeader('X-Pin-Token', $oldPinToken)->getJson(self::PROBE)->assertForbidden();
    }

    /**
     * @return array<string, array{list<int>|null}>
     */
    public static function staleSignIns(): array
    {
        return [
            'no fva claim' => [null],
            'never verified' => [[-1, -1]],
            'signed in six minutes ago' => [[6, -1]],
        ];
    }

    /**
     * @param  list<int>|null  $factorAges
     */
    #[DataProvider('staleSignIns')]
    public function test_resetting_the_pin_without_a_fresh_sign_in_is_refused(?array $factorAges): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken(['fva' => $factorAges]))
            ->postJson('/api/v1/merchant/pin/reset', ['new_pin' => '5678']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_RESET_REQUIRES_RECENT_LOGIN')
            ->assertJsonPath('error.details.max_age_seconds', 300);

        $this->assertTrue(Hash::check('1234', $merchant->fresh()->pin_hash));
    }

    private function unlock(string $clerkUserId = self::CLERK_USER): string
    {
        return $this->withToken($this->clerkToken($clerkUserId))
            ->postJson('/api/v1/merchant/pin/unlock', ['pin' => '1234'])
            ->json('data.pin_token');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function merchantToken(array $claims = []): string
    {
        return $this->clerkToken(self::CLERK_USER, $claims);
    }
}
