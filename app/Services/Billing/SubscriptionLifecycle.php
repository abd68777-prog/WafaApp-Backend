<?php

namespace App\Services\Billing;

use App\Enums\CardStatus;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\Payment;
use App\Notifications\SubscriptionNotification;
use App\Services\Merchant\MerchantState;
use Carbon\CarbonInterface;

/**
 * Moves a shop through TRIAL → ACTIVE → GRACE → EXPIRED as its dates pass
 * (requirements §3.2), and tells the merchant on the way (§7.2). Runs every
 * hour for every shop, and at once after a payment is approved or a trial
 * extended. Running it twice changes nothing: every step checks first.
 */
final class SubscriptionLifecycle
{
    public function __construct(private readonly SubscriptionLedger $ledger) {}

    public function sync(Merchant $merchant): void
    {
        // Not registered yet, or held by an admin decision the dates do not lift.
        if ($merchant->status === null || in_array($merchant->status, [MerchantStatus::Suspended, MerchantStatus::PendingDeletion, MerchantStatus::Deleted], true)) {
            return;
        }

        $previous = $merchant->status;
        $status = $this->ledger->statusFor($merchant);

        if ($status !== $previous) {
            $merchant->forceFill(['status' => $status])->save();
            $this->announce($merchant, $previous, $status);
        }

        if ($status === MerchantStatus::Trial || $status === MerchantStatus::Active) {
            $this->remindBeforeTheEnd($merchant, $status);
            $this->keepCardsWithinThePackage($merchant);
        }
    }

    private function announce(Merchant $merchant, MerchantStatus $from, MerchantStatus $to): void
    {
        if ($to === MerchantStatus::Grace) {
            $last = $this->ledger->lastPeriod($merchant);
            $merchant->notify(SubscriptionNotification::graceStarted($last, $this->daysUntil($last->grace_ends_at)));
        }

        if ($to === MerchantStatus::Expired && $from->canCollectStamps()) {
            $merchant->notify(SubscriptionNotification::subscriptionExpired());
        }
    }

    /**
     * Three days before the trial or the subscription runs out, once per end
     * date.
     */
    private function remindBeforeTheEnd(Merchant $merchant, MerchantStatus $status): void
    {
        $last = $this->ledger->lastPeriod($merchant);
        $days = $this->daysUntil($last->ends_at);

        if ($days < 1 || $days > MerchantState::ENDING_SOON_DAYS) {
            return;
        }

        $type = $status === MerchantStatus::Trial ? 'trial_ending' : 'subscription_ending';
        $key = SubscriptionNotification::reminderKey($type, $last, $last->ends_at);

        if ($merchant->notifications()->where('type', $type)->where('data->data->key', $key)->exists()) {
            return;
        }

        $merchant->notify($status === MerchantStatus::Trial
            ? SubscriptionNotification::trialEnding($last, $last->ends_at, $days)
            : SubscriptionNotification::subscriptionEnding($last, $last->ends_at, $days));
    }

    /**
     * After a move to a smaller package, the cards beyond its limit are
     * suspended when the new period starts, not when the merchant paid
     * (contract §5.9): the ones the merchant chose to keep stay, then the
     * oldest.
     */
    private function keepCardsWithinThePackage(Merchant $merchant): void
    {
        $period = $this->ledger->activePeriod($merchant);
        $limit = $period?->package?->cards_limit;
        $active = $merchant->cards()->where('status', CardStatus::Active)->orderBy('id')->get();

        if ($limit === null || $active->count() <= $limit) {
            return;
        }

        $keep = Payment::query()->where('subscription_period_id', $period->id)->first()?->keep_card_ids ?? [];
        $ordered = $active->sortBy(fn ($card): int => in_array($card->id, $keep, true) ? 0 : 1)->values();

        foreach ($ordered->slice($limit) as $card) {
            $card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();
        }
    }

    private function daysUntil(?CarbonInterface $moment): int
    {
        return $moment === null ? 0 : max(0, (int) ceil(now()->diffInSeconds($moment, false) / 86400));
    }
}
