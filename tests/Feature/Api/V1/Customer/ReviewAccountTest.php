<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\CardCycleStatus;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\OtpCode;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReviewAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

        $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::REVIEW_PHONE,
            'code' => self::REVIEW_CODE,
            'name' => 'Store Review',
            'birthdate' => '1990-01-01',
            'policy_version' => '1.2',
        ]);

        $response->assertOk()->assertJsonPath('data.phone', self::REVIEW_PHONE);

        Http::assertNothingSent();
    }

    public function test_any_other_number_still_gets_a_real_code_and_the_fixed_code_fails_for_it(): void
    {
        config(['otp.review_phone' => self::REVIEW_PHONE, 'otp.review_code' => self::REVIEW_CODE]);
        Http::fake(['api.lightotp.com/SendMessage' => Http::response(['id' => 'a1', 'messageStatus' => 'Sent'])]);

        $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => '+963947123456'])->assertOk();

        Http::assertSentCount(1);

        $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => '+963947123456',
            'code' => self::REVIEW_CODE,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
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

        $this->postJson('/api/v1/customer/auth/otp/request', ['phone' => self::REVIEW_PHONE])->assertOk();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('otp_codes', 1);
        $this->assertFalse(Hash::check(self::REVIEW_CODE, OtpCode::query()->sole()->code_hash));
    }

    public function test_the_seeder_gives_the_review_account_a_card_in_progress_and_a_ready_reward(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['otp.review_phone' => self::REVIEW_PHONE]);

        $this->seed(ReviewAccountSeeder::class);
        $this->seed(ReviewAccountSeeder::class);

        $reviewer = Customer::query()->where('phone', self::REVIEW_PHONE)->sole();
        $this->assertFalse($reviewer->isPending());
        $this->assertNotNull($reviewer->qr_secret);
        $this->assertSame(1, $reviewer->policyConsents()->count());

        $cycles = CardCycle::query()->where('customer_id', $reviewer->id)->orderBy('id')->get();
        $this->assertCount(2, $cycles);
        $this->assertSame([CardCycleStatus::Collecting, CardCycleStatus::RewardReady], $cycles->pluck('status')->all());
        $this->assertSame([3, 5], $cycles->pluck('stamps_count')->all());
        $this->assertSame(8, $reviewer->stamps()->count());

        $this->assertSame(2, Merchant::query()->where('clerk_user_id', 'like', 'review_demo_%')->count());
    }
}
