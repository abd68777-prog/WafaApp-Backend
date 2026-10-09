<?php

namespace App\Services\Billing;

use App\Enums\MerchantStatus;
use App\Enums\SubscriptionPeriodType;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\SubscriptionPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reads a merchant's subscription from its periods (requirements §3).
 *
 * Periods chain: a renewal starts where the previous one ends, so a shop may
 * hold a paid period that has not started yet. Two dates matter, and they
 * are not the same period:
 *
 * - the **active** period covers now and decides the package and its limits;
 * - the **chain end** is when the subscription as a whole runs out, which is
 *   what the countdown, the banner and the reminders count to.
 */
final class SubscriptionLedger
{
    /**
     * The period covering now; when two do (an upgrade starts before the
     * period it replaces ends), the later start wins. Without one, the most
     * recent past period, so an expired shop still shows its last package.
     */
    public function activePeriod(Merchant $merchant): ?SubscriptionPeriod
    {
        return $this->activeAmong($this->periods($merchant));
    }

    /**
     * The same choice among periods already loaded, so a list of shops can
     * pick each one's period from a single query.
     *
     * @param  Collection<int, SubscriptionPeriod>  $periods
     */
    public function activeAmong(Collection $periods): ?SubscriptionPeriod
    {
        return $periods
            ->filter(fn (SubscriptionPeriod $period): bool => $this->covers($period, now()))
            ->sortBy([['starts_at', 'desc'], ['id', 'desc']])
            ->first()
            ?? $periods
                ->filter(fn (SubscriptionPeriod $period): bool => $period->starts_at->lte(now()))
                ->sortBy([['ends_at', 'desc'], ['id', 'desc']])
                ->first();
    }

    /**
     * When the subscription runs out: the end of the period ending last.
     */
    public function chainEnd(Merchant $merchant): ?CarbonInterface
    {
        return $this->lastPeriod($merchant)?->ends_at;
    }

    /**
     * The period ending last; its grace days, if paid, follow the chain.
     */
    public function lastPeriod(Merchant $merchant): ?SubscriptionPeriod
    {
        return $this->periods($merchant)->sortBy([['ends_at', 'desc'], ['id', 'desc']])->first();
    }

    /**
     * The status the dates call for (requirements §3.2). Suspension and
     * deletion are admin decisions and are not derived here.
     */
    public function statusFor(Merchant $merchant): MerchantStatus
    {
        $periods = $this->periods($merchant);
        $now = now();

        if ($periods->contains(fn (SubscriptionPeriod $period): bool => $this->covers($period, $now))) {
            $paidAhead = $periods->contains(fn (SubscriptionPeriod $period): bool => $period->type === SubscriptionPeriodType::Paid && $period->ends_at->gt($now));

            return $paidAhead ? MerchantStatus::Active : MerchantStatus::Trial;
        }

        $last = $this->lastPeriod($merchant);

        if ($last?->type === SubscriptionPeriodType::Paid && $last->grace_ends_at?->gt($now)) {
            return MerchantStatus::Grace;
        }

        return MerchantStatus::Expired;
    }

    /**
     * Where an approved payment's period goes (requirements §3.5 and §5.2):
     *
     * - while the subscription runs, and during the grace days, it continues
     *   from the old end date, so paying early loses nothing and paying late
     *   gains nothing;
     * - once expired, it starts today;
     * - an upgrade starts today but still ends where the renewal would have,
     *   so the higher package also covers the days already paid for.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, grace_ends_at: CarbonImmutable}
     */
    public function placeNewPeriod(Merchant $merchant, Package $package, int $months): array
    {
        $now = CarbonImmutable::now();
        $chainEnd = $this->chainEnd($merchant)?->toImmutable();
        $runningOrInGrace = $chainEnd !== null
            && ($chainEnd->gt($now) || $this->statusFor($merchant) === MerchantStatus::Grace);

        $base = $runningOrInGrace ? $chainEnd : $now;
        $endsAt = $base->addMonthsNoOverflow($months);

        return [
            'starts_at' => $this->isUpgrade($merchant, $package) ? $now : $base,
            'ends_at' => $endsAt,
            'grace_ends_at' => $endsAt->addDays((int) Setting::read('grace_days', 3)),
        ];
    }

    /**
     * A package with more cards, or as many cards and more campaigns, than
     * the one the shop is on now.
     */
    public function isUpgrade(Merchant $merchant, Package $package): bool
    {
        $current = $this->activePeriod($merchant)?->package;

        if ($current === null) {
            return false;
        }

        return $package->cards_limit > $current->cards_limit
            || ($package->cards_limit === $current->cards_limit && $package->weekly_campaigns_limit > $current->weekly_campaigns_limit);
    }

    public function hasPaid(Merchant $merchant): bool
    {
        return $this->periods($merchant)->contains('type', SubscriptionPeriodType::Paid);
    }

    /**
     * @return Collection<int, SubscriptionPeriod>
     */
    private function periods(Merchant $merchant): Collection
    {
        return $merchant->subscriptionPeriods()->with('package')->get();
    }

    private function covers(SubscriptionPeriod $period, CarbonInterface $moment): bool
    {
        return $period->starts_at->lte($moment) && $period->ends_at->gt($moment);
    }
}
