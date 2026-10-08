<?php

namespace Tests\Feature\Billing;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\SubscriptionPeriod;
use App\Services\Billing\SubscriptionLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The subscription read from its periods (requirements §3): the status the
 * dates call for, the package in force, and where a paid period goes.
 */
class SubscriptionLedgerTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionLedger $ledger;

    private Merchant $merchant;

    private Package $basic;

    private Package $medium;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
        Setting::write('grace_days', 3);
        $this->ledger = new SubscriptionLedger;
        $this->merchant = Merchant::factory()->create();
        $this->basic = Package::factory()->create(['cards_limit' => 1, 'weekly_campaigns_limit' => 1]);
        $this->medium = Package::factory()->create(['cards_limit' => 2, 'weekly_campaigns_limit' => 2]);
    }

    public function test_the_status_follows_the_dates(): void
    {
        $this->assertSame(MerchantStatus::Expired, $this->ledger->statusFor($this->merchant));

        $trial = $this->period('trial', '2026-09-20', '2026-10-04');
        $this->assertSame(MerchantStatus::Trial, $this->ledger->statusFor($this->merchant));

        $trial->forceFill(['ends_at' => '2026-09-30'])->save();
        $this->assertSame(MerchantStatus::Expired, $this->ledger->statusFor($this->merchant), 'no grace after a trial');

        $this->period('paid', '2026-09-01', '2026-10-15');
        $this->assertSame(MerchantStatus::Active, $this->ledger->statusFor($this->merchant));

        $this->travelTo('2026-10-16 12:00:00');
        $this->assertSame(MerchantStatus::Grace, $this->ledger->statusFor($this->merchant));

        $this->travelTo('2026-10-18 12:00:01');
        $this->assertSame(MerchantStatus::Expired, $this->ledger->statusFor($this->merchant));
    }

    public function test_paying_during_the_trial_makes_the_shop_active_at_once(): void
    {
        $this->period('trial', '2026-09-25', '2026-10-09');
        $this->period('paid', '2026-10-09', '2026-11-09');

        $this->assertSame(MerchantStatus::Active, $this->ledger->statusFor($this->merchant));
    }

    public function test_a_renewal_paid_in_advance_does_not_change_the_package_before_it_starts(): void
    {
        $this->period('paid', '2026-09-15', '2026-10-15', $this->medium);
        $this->period('paid', '2026-10-15', '2026-11-15', $this->basic);

        $this->assertTrue($this->ledger->activePeriod($this->merchant)->package->is($this->medium));
        $this->assertSame('2026-11-15', $this->ledger->chainEnd($this->merchant)->toDateString());
    }

    public function test_a_renewal_continues_from_the_old_end_date_while_the_subscription_runs(): void
    {
        $this->period('paid', '2026-09-15', '2026-10-15');

        $placement = $this->ledger->placeNewPeriod($this->merchant, $this->basic, 3);

        $this->assertSame('2026-10-15', $placement['starts_at']->toDateString());
        $this->assertSame('2027-01-15', $placement['ends_at']->toDateString());
        $this->assertSame('2027-01-18', $placement['grace_ends_at']->toDateString());
    }

    public function test_paying_during_the_grace_days_gains_nothing(): void
    {
        $this->period('paid', '2026-08-30', '2026-09-30');

        $placement = $this->ledger->placeNewPeriod($this->merchant, $this->basic, 1);

        $this->assertSame('2026-09-30', $placement['starts_at']->toDateString());
        $this->assertSame('2026-10-30', $placement['ends_at']->toDateString());
    }

    public function test_an_expired_shop_starts_again_today(): void
    {
        $this->period('paid', '2026-08-01', '2026-09-01');

        $placement = $this->ledger->placeNewPeriod($this->merchant, $this->basic, 1);

        $this->assertSame('2026-10-01 12:00:00', $placement['starts_at']->toDateTimeString());
        $this->assertSame('2026-11-01', $placement['ends_at']->toDateString());
    }

    public function test_an_upgrade_starts_today_and_ends_where_the_renewal_would(): void
    {
        $this->period('paid', '2026-09-21', '2026-10-21', $this->basic);

        $placement = $this->ledger->placeNewPeriod($this->merchant, $this->medium, 1);

        $this->assertSame('2026-10-01 12:00:00', $placement['starts_at']->toDateTimeString());
        $this->assertSame('2026-11-21', $placement['ends_at']->toDateString());
    }

    private function period(string $type, string $startsAt, string $endsAt, ?Package $package = null): SubscriptionPeriod
    {
        $factory = SubscriptionPeriod::factory()->for($this->merchant)->for($package ?? $this->basic);

        return ($type === 'trial' ? $factory->trial() : $factory)->create([
            'starts_at' => $startsAt.' 12:00:00',
            'ends_at' => $endsAt.' 12:00:00',
            'grace_ends_at' => $type === 'trial' ? null : now()->parse($endsAt.' 12:00:00')->addDays(3),
        ]);
    }
}
