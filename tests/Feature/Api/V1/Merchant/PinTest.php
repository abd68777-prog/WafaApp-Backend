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
 * The PIN separates the owner from the cashier on one shared account: the
 * server refuses the protected tabs without proof of it, and changing it takes
 * a fresh Clerk sign-in the cashier cannot pass.
 */
class PinTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_merchant_1';

    private const PROBE = '/api/v1/merchant/pin-probe';

    protected function setUp(): void
    {
        parent::setUp();

        // No protected tab exists yet, so a stand-in route carries the same
        // middleware stack the tabs will use.
        Route::middleware(['api', 'app.version', 'clerk', 'clerk.merchant', 'merchant.pin'])
            ->get(self::PROBE, fn () => ['opened' => true]);
    }

    public function test_the_correct_pin_returns_a_token_that_opens_the_protected_tabs(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        Setting::write('pin_unlock_hours', 12);
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->postJson('/api/v1/merchant/pin/verify', ['pin' => '1234']);

        $response->assertOk()->assertJsonPath('expires_at', '2026-09-24T00:00:00+00:00');

        $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $response->json('pin_token'))
            ->getJson(self::PROBE)
            ->assertOk()
            ->assertJsonPath('opened', true);
    }

    public function test_the_protected_tabs_refuse_a_request_without_the_pin(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())->getJson(self::PROBE);

        $response->assertForbidden()->assertJsonPath('code', 'pin_required');
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
            ->assertJsonPath('code', 'pin_required');
    }

    public function test_an_unlock_token_opens_only_the_shop_it_was_issued_for(): void
    {
        Merchant::factory()->create(['clerk_user_id' => 'user_other_shop']);
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $otherShopsToken = $this->unlock('user_other_shop');

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $otherShopsToken)
            ->getJson(self::PROBE);

        $response->assertForbidden()->assertJsonPath('code', 'pin_required');
    }

    public function test_a_forged_unlock_token_is_refused(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', base64_encode('{"merchant":1}'))
            ->getJson(self::PROBE);

        $response->assertForbidden()->assertJsonPath('code', 'pin_required');
    }

    public function test_a_fresh_clerk_sign_in_sets_a_new_pin_and_voids_old_unlock_tokens(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $oldPinToken = $this->unlock();

        $response = $this->withToken($this->merchantToken(['fva' => [2, -1]]))->putJson('/api/v1/merchant/pin', [
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('5678', $merchant->fresh()->pin_hash));
        $this->withToken($this->merchantToken())
            ->withHeader('X-Pin-Token', $oldPinToken)
            ->getJson(self::PROBE)
            ->assertForbidden();
    }

    /**
     * @return array<string, array{list<int>|null}>
     */
    public static function staleVerifications(): array
    {
        return [
            'no fva claim' => [null],
            'never verified' => [[-1, -1]],
            'verified eleven minutes ago' => [[11, -1]],
        ];
    }

    /**
     * @param  list<int>|null  $factorAges
     */
    #[DataProvider('staleVerifications')]
    public function test_changing_the_pin_without_a_fresh_sign_in_asks_for_clerk_reverification(?array $factorAges): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken(['fva' => $factorAges]))->putJson('/api/v1/merchant/pin', [
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ]);

        // The shape Clerk's useReverification() hook recognises.
        $response->assertForbidden()
            ->assertJsonPath('code', 'reverification_required')
            ->assertJsonPath('clerk_error.type', 'forbidden')
            ->assertJsonPath('clerk_error.reason', 'reverification-error')
            ->assertJsonPath('clerk_error.metadata.reverification', ['level' => 'first_factor', 'afterMinutes' => 10]);

        $this->assertTrue(Hash::check('1234', $merchant->fresh()->pin_hash));
    }

    public function test_a_new_pin_must_be_confirmed(): void
    {
        Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->merchantToken(['fva' => [1, -1]]))->putJson('/api/v1/merchant/pin', [
            'pin' => '5678',
            'pin_confirmation' => '8765',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('pin');
    }

    private function unlock(string $clerkUserId = self::CLERK_USER): string
    {
        return $this->withToken($this->clerkToken($clerkUserId))
            ->postJson('/api/v1/merchant/pin/verify', ['pin' => '1234'])
            ->json('pin_token');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function merchantToken(array $claims = []): string
    {
        return $this->clerkToken(self::CLERK_USER, $claims);
    }
}
