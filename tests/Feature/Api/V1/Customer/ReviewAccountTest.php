<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Campaign;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Merchant;
use App\Models\OtpCode;
use App\Models\Setting;
use App\Models\Stamp;
use App\Services\Customer\CustomerQrCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Apple and Google reviewers sign in with a fixed number and a fixed code
 * (store upload notes). Nothing is sent for it, and no other number accepts
 * that code.
 */
class ReviewAccountTest extends TestCase
{
    use RefreshDatabase;

    private const REVIEW_PHONE = '+963900000999';

    private const REVIEW_CODE = '246810';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'otp.driver' => 'lightotp',
            'services.lightotp.key' => 'test-key',
            'services.lightotp.base_url' => 'https://api.lightotp.com',
        ]);
    }

    public function test_the_review_number_signs_in_with_the_fixed_code_without_sending_anything(): void
    {
        Http::preventStrayRequests();
        config(['otp.review_phone' => '0900 000 999', 'otp.review_code' => self::REVIEW_CODE]);

        $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::REVIEW_PHONE])->assertAccepted();

        $response = $this->postJson('/api/v1/customer/auth/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => self::REVIEW_CODE,
            'policy_version' => '1.0',
        ]);

        $response->assertOk()->assertJsonPath('data.customer.phone', self::REVIEW_PHONE);

        Http::assertNothingSent();
    }

    public function test_any_other_number_still_gets_a_real_code_and_the_fixed_code_fails_for_it(): void
    {
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);
        Http::fake(['api.lightotp.com/SendMessage' => Http::response(['id' => 'a1', 'messageStatus' => 'Sent'])]);

        $this->postJson('/api/v1/customer/auth/otp', ['phone' => '+963947123456'])->assertAccepted();

        Http::assertSentCount(1);

        $this->postJson('/api/v1/customer/auth/verify', [
            'phone' => '+963947123456',
            'code' => self::REVIEW_CODE,
            'policy_version' => '1.0',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'OTP_INVALID');
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function switchedOffSettings(): array
    {
        return [
            'no number' => [null, self::REVIEW_CODE],
            'no code' => [self::REVIEW_PHONE, null],
            'code of the wrong length' => [self::REVIEW_PHONE, '1234'],
        ];
    }

    #[DataProvider('switchedOffSettings')]
    public function test_the_review_number_takes_the_normal_path_when_the_feature_is_off(?string $phone, ?string $code): void
    {
        config(['otp.review_phone' => $phone, 'otp.review_code' => $code]);
        Http::fake(['api.lightotp.com/SendMessage' => Http::response(['id' => 'a1', 'messageStatus' => 'Sent'])]);

        $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::REVIEW_PHONE])->assertAccepted();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('otp_codes', 1);
        $this->assertFalse(Hash::check(self::REVIEW_CODE, OtpCode::query()->sole()->code_hash));
    }

    public function test_a_reviewer_finds_every_screen_populated(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);

        $this->artisan('review:prepare')->assertSuccessful();

        $token = $this->signInAsReviewer();

        $cards = $this->withToken($token)->getJson('/api/v1/customer/cards')->assertOk()->json('data');
        $this->assertCount(3, $cards);

        $this->withToken($token)->getJson('/api/v1/customer/directory')->assertOk()->assertJsonCount(4, 'data');
        $contacts = $this->withToken($token)->getJson('/api/v1/customer/shop-contacts')->assertOk()->json('data');
        $this->assertCount(4, $contacts);
        $this->assertNotContains(null, array_column($contacts, 'address'));
        $this->assertNotContains(null, array_column($contacts, 'contact_phone'));

        $this->withToken($token)->getJson('/api/v1/customer/notifications')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonPath('data.0.type', 'stamp_added');

        $reviewer = Customer::query()->where('phone', self::REVIEW_PHONE)->sole();
        $this->assertSame(
            ['COLLECTING', 'REWARD_READY', 'REDEEMED', 'COLLECTING'],
            CardCycle::query()->where('customer_id', $reviewer->id)->orderBy('id')->pluck('status')->map->value->all(),
        );
        $this->assertSame(1, Campaign::query()->count());
    }

    public function test_preparing_again_adds_nothing_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE]);

        $this->artisan('review:prepare')->assertSuccessful();
        $counts = $this->counts();

        $this->artisan('review:prepare')->assertSuccessful();

        $this->assertSame($counts, $this->counts());
        $this->assertSame([4, 4, 4, 13, 4], array_values($counts));
    }

    public function test_after_the_reviewer_deletes_the_account_the_next_sign_in_is_filled_again(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);
        $this->artisan('review:prepare')->assertSuccessful();

        $this->withToken($this->signInAsReviewer())->deleteJson('/api/v1/customer/me')->assertNoContent();
        $this->app['auth']->forgetGuards();

        // A brand-new account: the app asks for the name and birthdate first.
        $token = $this->signInAsReviewer();
        $this->withToken($token)->getJson('/api/v1/customer/cards')->assertJsonPath('error.code', 'PROFILE_INCOMPLETE');

        $this->artisan('review:prepare')->assertSuccessful();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/customer/cards')->assertJsonCount(3, 'data');
        $this->withToken($token)->getJson('/api/v1/customer/notifications')->assertJsonPath('meta.unread_count', 2);
        $this->assertSame(4, Merchant::query()->where('clerk_user_id', 'like', 'review_demo_%')->count());
    }

    public function test_a_deleted_account_is_refilled_before_anyone_signs_in_again(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);
        $this->artisan('review:prepare')->assertSuccessful();

        $this->withToken($this->signInAsReviewer())->deleteJson('/api/v1/customer/me')->assertNoContent();
        $this->app['auth']->forgetGuards();

        // The hourly run, with nobody signed in.
        $this->artisan('review:prepare')->assertSuccessful();

        $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::REVIEW_PHONE])->assertAccepted();
        $response = $this->postJson('/api/v1/customer/auth/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => self::REVIEW_CODE,
            'policy_version' => Setting::currentPolicyVersion(),
        ])->assertOk()->assertJsonPath('data.needs_profile', false);

        $this->withToken($response->json('data.token'))->getJson('/api/v1/customer/cards')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_the_refill_restores_the_demo_profile_over_what_a_reviewer_typed(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);
        $this->artisan('review:prepare')->assertSuccessful();

        $this->withToken($this->signInAsReviewer())->deleteJson('/api/v1/customer/me')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $token = $this->signInAsReviewer();
        $this->withToken($token)->postJson('/api/v1/customer/me/profile', ['name' => 'Apple Reviewer', 'birthdate' => '1985-05-05'])->assertSuccessful();

        $this->artisan('review:prepare')->assertSuccessful();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/customer/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Store Review')
            ->assertJsonPath('data.birthdate', '1990-01-01');
        $this->withToken($token)->getJson('/api/v1/customer/cards')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_reviewers_sharing_the_number_are_never_held_back_by_the_code_limits(): void
    {
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);

        foreach (range(1, 6) as $attempt) {
            $this->signInAsReviewer();
        }

        $this->assertSame(6, OtpCode::query()->whereNotNull('consumed_at')->count());
    }

    public function test_a_real_shop_cannot_scan_or_type_the_review_number(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE]);
        $this->artisan('review:prepare')->assertSuccessful();

        $reviewer = Customer::query()->where('phone', self::REVIEW_PHONE)->sole();
        $shop = Merchant::factory()->create(['clerk_user_id' => 'user_real_shop']);
        $card = Card::factory()->for($shop)->create();
        $qr = 'W1.'.$reviewer->qr_id.'.'.CustomerQrCode::codeAt($reviewer->qr_secret, now()->getTimestamp(), app(CustomerQrCode::class)->period());

        $this->withToken($this->clerkToken('user_real_shop'))
            ->postJson('/api/v1/merchant/scan/resolve', ['card_id' => $card->id, 'qr' => $qr])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QR_INVALID');

        $this->withToken($this->clerkToken('user_real_shop'))
            ->postJson('/api/v1/merchant/scan/resolve', ['card_id' => $card->id, 'phone' => '0900000999'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['phone' => ['invalid']]);

        $this->assertSame(0, CardCycle::query()->where('merchant_id', $shop->id)->count());
    }

    public function test_the_refill_fills_the_inbox_without_pushing_to_the_reviewers_phone(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);
        Queue::fake();

        $this->signInAsReviewer();
        DeviceToken::factory()->for(Customer::query()->where('phone', self::REVIEW_PHONE)->sole(), 'owner')->create();

        $this->artisan('review:prepare')->assertSuccessful();

        $this->assertSame(4, Customer::query()->where('phone', self::REVIEW_PHONE)->sole()->notifications()->count());
        Queue::assertNothingPushed();
    }

    public function test_the_demo_shops_are_seen_by_the_review_account_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE]);
        $this->artisan('review:prepare')->assertSuccessful();

        $realShop = Merchant::factory()->create();
        Card::factory()->for($realShop)->create();
        $demoShop = Merchant::query()->where('clerk_user_id', 'review_demo_cafe')->sole();
        $token = Customer::factory()->consented()->create()->createToken('phone', ['customer'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/customer/directory')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $realShop->id);
        $this->withToken($token)->getJson('/api/v1/customer/shop-contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $realShop->id);
        $this->withToken($token)->getJson('/api/v1/customer/merchants/'.$demoShop->id)->assertNotFound();
    }

    public function test_nothing_is_prepared_while_the_review_number_is_unset(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => null]);

        $this->artisan('review:prepare')->assertSuccessful();

        $this->assertSame(0, Merchant::query()->where('clerk_user_id', 'like', 'review_demo_%')->count());
    }

    private function signInAsReviewer(): string
    {
        Http::preventStrayRequests();
        $this->postJson('/api/v1/customer/auth/otp', ['phone' => self::REVIEW_PHONE])->assertAccepted();

        return $this->postJson('/api/v1/customer/auth/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => self::REVIEW_CODE,
            'policy_version' => Setting::currentPolicyVersion(),
        ])->assertOk()->json('data.token');
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'shops' => Merchant::query()->where('clerk_user_id', 'like', 'review_demo_%')->count(),
            'cards' => Card::query()->count(),
            'cycles' => CardCycle::query()->count(),
            'stamps' => Stamp::query()->count(),
            'notifications' => Customer::query()->where('phone', self::REVIEW_PHONE)->sole()->notifications()->count(),
        ];
    }
}
