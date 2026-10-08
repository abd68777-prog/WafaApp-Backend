<?php

namespace Tests\Feature\Console;

use App\Enums\CardStatus;
use App\Enums\MerchantStatus;
use App\Models\Card;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The hourly pass over every shop (requirements §3.2 and §7.2): statuses
 * follow the dates, each change is announced, reminders come once, and a
 * smaller package suspends the extra cards when it starts.
 */
class SyncSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
    }

    public function test_a_trial_that_runs_out_expires_with_no_grace_days_and_says_so(): void
    {
        $merchant = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial()->for($merchant)->create(['starts_at' => '2026-09-17 12:00:00', 'ends_at' => '2026-10-01 11:00:00']);

        $this->artisan('subscriptions:sync')->assertSuccessful();

        $this->assertSame(MerchantStatus::Expired, $merchant->fresh()->status);
        $this->assertSame(
            'اشتراكك منتهٍ والطوابع متوقفة. تسليم الهدايا ما زال متاحاً.',
            $merchant->notifications()->where('type', 'subscription_expired')->sole()->data['body'],
        );
    }

    public function test_a_paid_subscription_reminds_then_enters_grace_then_expires(): void
    {
        $merchant = Merchant::factory()->active()->create();
        SubscriptionPeriod::factory()->for($merchant)->create([
            'starts_at' => '2026-09-03 12:00:00',
            'ends_at' => '2026-10-03 12:00:00',
            'grace_ends_at' => '2026-10-06 12:00:00',
        ]);

        $this->artisan('subscriptions:sync');
        $this->artisan('subscriptions:sync');
        $this->assertSame(['subscription_ending'], $this->types($merchant));
        $this->assertSame('ينتهي اشتراكك بعد يومين.', $merchant->notifications()->sole()->data['body']);

        $this->travelTo('2026-10-03 13:00:00');
        $this->artisan('subscriptions:sync');
        $this->assertSame(MerchantStatus::Grace, $merchant->fresh()->status);
        $this->assertSame(
            'انتهى اشتراكك، ولديك مهلة 3 أيام قبل توقف الطوابع.',
            $merchant->notifications()->where('type', 'grace_started')->sole()->data['body'],
        );

        $this->travelTo('2026-10-06 13:00:00');
        $this->artisan('subscriptions:sync');
        $this->assertSame(MerchantStatus::Expired, $merchant->fresh()->status);
        $this->assertSame(['subscription_ending', 'grace_started', 'subscription_expired'], $this->types($merchant));
    }

    public function test_the_trial_reminder_comes_once_three_days_before(): void
    {
        $merchant = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial()->for($merchant)->create(['ends_at' => '2026-10-05 12:00:00']);

        $this->artisan('subscriptions:sync');
        $this->assertSame([], $this->types($merchant), 'four days before: not yet');

        $this->travelTo('2026-10-02 13:00:00');
        $this->artisan('subscriptions:sync');
        $this->artisan('subscriptions:sync');

        $this->assertSame(['trial_ending'], $this->types($merchant));
        $this->assertSame('تنتهي تجربتك المجانية بعد 3 أيام. اشترك لتكمل.', $merchant->notifications()->sole()->data['body']);
    }

    public function test_a_smaller_package_suspends_the_cards_not_kept_when_it_starts(): void
    {
        $merchant = Merchant::factory()->active()->create();
        $medium = Package::factory()->create(['cards_limit' => 3]);
        $basic = Package::factory()->create(['cards_limit' => 1]);
        [$coffee, $tea, $cake] = Card::factory()->for($merchant)->count(3)->create();
        SubscriptionPeriod::factory()->for($merchant)->for($medium)->create(['starts_at' => '2026-09-01 12:00:00', 'ends_at' => '2026-10-02 12:00:00']);
        $renewal = SubscriptionPeriod::factory()->for($merchant)->for($basic)->create(['starts_at' => '2026-10-02 12:00:00', 'ends_at' => '2026-11-02 12:00:00']);
        Payment::factory()->for($merchant)->for($basic)->approved()->create(['subscription_period_id' => $renewal->id, 'keep_card_ids' => [$tea->id]]);

        $this->artisan('subscriptions:sync');
        $this->assertSame(3, $merchant->cards()->where('status', CardStatus::Active)->count(), 'the old package still runs');

        $this->travelTo('2026-10-02 13:00:00');
        $this->artisan('subscriptions:sync');

        $this->assertSame(CardStatus::Suspended, $coffee->fresh()->status);
        $this->assertSame(CardStatus::Active, $tea->fresh()->status);
        $this->assertSame(CardStatus::Suspended, $cake->fresh()->status);
    }

    public function test_suspended_shops_are_left_alone(): void
    {
        $merchant = Merchant::factory()->suspended()->create();
        SubscriptionPeriod::factory()->trial()->for($merchant)->create(['ends_at' => '2026-09-01 12:00:00']);

        $this->artisan('subscriptions:sync');

        $this->assertSame(MerchantStatus::Suspended, $merchant->fresh()->status);
        $this->assertSame([], $this->types($merchant));
    }

    /**
     * @return list<string>
     */
    private function types(Merchant $merchant): array
    {
        return $merchant->notifications()->reorder('id')->pluck('type')->all();
    }
}
