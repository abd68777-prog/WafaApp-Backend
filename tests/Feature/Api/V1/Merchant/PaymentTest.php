<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SubscriptionPeriod;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Paying for the subscription from the merchant app (contract §5.9): the
 * proof goes into the review queue with the prices of the moment.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    private Package $medium;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local', ['serve' => true]);
        Setting::write('exchange_rate_syp', 13000);
        $this->medium = Package::factory()->withPrices(17)->create(['cards_limit' => 2]);
        $this->merchant = Merchant::factory()->onPackage($this->medium)->create(['clerk_user_id' => self::CLERK_USER]);
    }

    public function test_the_proof_joins_the_queue_with_the_price_and_rate_of_the_moment(): void
    {
        $response = $this->submit(['package_id' => $this->medium->id, 'duration_months' => 1, 'reference' => 'TX123']);

        $response->assertCreated()
            ->assertJsonPath('data.package.id', $this->medium->id)
            ->assertJsonPath('data.price_usd', '17.00')
            ->assertJsonPath('data.exchange_rate', '13000.0000')
            ->assertJsonPath('data.amount_syp', '221000.00')
            ->assertJsonPath('data.method', 'syriatel_cash')
            ->assertJsonPath('data.reference', 'TX123')
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.rejection_reason', null);

        $payment = Payment::query()->sole();
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertNotEmpty($response->json('data.proof_url'));

        // A later price change does not touch the payment.
        $this->medium->prices()->where('duration_months', 1)->update(['price_usd' => 25]);
        $this->assertSame('17.00', (string) $payment->fresh()->price_usd);
    }

    public function test_one_payment_waits_for_review_at_a_time(): void
    {
        $pending = Payment::factory()->for($this->merchant)->for($this->medium)->create();

        $this->submit(['package_id' => $this->medium->id, 'duration_months' => 1])
            ->assertConflict()
            ->assertJsonPath('error.code', 'PAYMENT_ALREADY_PENDING')
            ->assertJsonPath('error.details.payment_id', $pending->id);
    }

    public function test_a_duration_without_a_price_is_refused(): void
    {
        $this->medium->prices()->where('duration_months', 12)->delete();

        $this->submit(['package_id' => $this->medium->id, 'duration_months' => 12])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PRICE_NOT_AVAILABLE');
    }

    public function test_moving_to_a_smaller_package_asks_which_cards_stay(): void
    {
        $basic = Package::factory()->withPrices(10)->create(['cards_limit' => 1]);
        [$coffee, $tea] = Card::factory()->for($this->merchant)->count(2)->create();

        $this->submit(['package_id' => $basic->id, 'duration_months' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'KEEP_CARDS_REQUIRED')
            ->assertJsonPath('error.details', ['cards_limit' => 1, 'active_cards' => 2]);

        $this->submit(['package_id' => $basic->id, 'duration_months' => 1, 'keep_card_ids' => [$coffee->id, $tea->id]])
            ->assertJsonPath('error.code', 'KEEP_CARDS_REQUIRED');

        $this->submit(['package_id' => $basic->id, 'duration_months' => 1, 'keep_card_ids' => [$tea->id]])->assertCreated();

        $this->assertSame([$tea->id], Payment::query()->sole()->keep_card_ids);
        $this->assertSame(CardStatus::Active, $coffee->fresh()->status, 'nothing is suspended at upload');
    }

    public function test_the_fields_are_validated(): void
    {
        $this->withPin()->post('/api/v1/merchant/payments', [
            'package_id' => $this->medium->id,
            'duration_months' => 2,
            'method' => 'cash',
            'proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', [
                'duration_months' => ['format'],
                'method' => ['format'],
                'proof' => ['format'],
            ]);
    }

    public function test_the_history_lists_payments_newest_first_and_the_subscription_lists_its_periods(): void
    {
        $older = Payment::factory()->for($this->merchant)->for($this->medium)->rejected()->create();
        $newer = Payment::factory()->for($this->merchant)->for($this->medium)->create();
        SubscriptionPeriod::factory()->for($this->merchant)->for($this->medium)->create([
            'starts_at' => now()->addDays(14), 'ends_at' => now()->addDays(14)->addMonth(),
        ]);

        $firstPage = $this->withPin()->getJson('/api/v1/merchant/payments?limit=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.proof_url', fn (string $url): bool => $url !== '');

        $this->withPin()->getJson('/api/v1/merchant/payments?limit=1&cursor='.$firstPage->json('meta.next_cursor'))
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.0.rejection_reason', 'transfer_not_received');

        $this->withPin()->getJson('/api/v1/merchant/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'TRIAL')
            ->assertJsonPath('data.current_period.type', 'trial')
            ->assertJsonPath('data.pending_payment.id', $newer->id)
            ->assertJsonCount(2, 'data.periods')
            ->assertJsonPath('data.periods.0.type', 'paid');
    }

    public function test_payments_are_behind_the_pin(): void
    {
        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->getJson('/api/v1/merchant/payments')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function submit(array $fields): TestResponse
    {
        return $this->withPin()->post('/api/v1/merchant/payments', [
            'method' => 'syriatel_cash',
            'proof' => UploadedFile::fake()->image('proof.jpg'),
            ...$fields,
        ], ['Accept' => 'application/json']);
    }

    private function withPin(): self
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token']);
    }
}
