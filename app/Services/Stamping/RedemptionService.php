<?php

namespace App\Services\Stamping;

use App\Enums\CardCycleStatus;
use App\Enums\CardStatus;
use App\Enums\ErrorCode;
use App\Enums\StampMethod;
use App\Exceptions\ApiException;
use App\Models\CardCycle;
use App\Models\Merchant;
use App\Notifications\RewardRedeemed;

/**
 * Hands over a reward (contract merchantRedeemReward, transition 05).
 *
 * Atomic: the cycle moves to REDEEMED only if it is still REWARD_READY, in one
 * conditional update. When two devices confirm at the same moment, one wins
 * and the other is told the reward was just handed over.
 *
 * Allowed in every non-final merchant status: the reward belongs to the
 * customer, not to the merchant's billing state.
 */
final class RedemptionService
{
    public function __construct(private readonly ScanToken $scanToken) {}

    public function redeem(Merchant $merchant, string $scanToken, int $cycleId): CardCycle
    {
        $ticket = $this->scanToken->read($merchant, $scanToken);

        // Only the customer in front of the cashier, showing their code, can
        // receive a reward (decision 5).
        if ($ticket->method !== StampMethod::Qr) {
            throw ApiException::of(ErrorCode::RedeemRequiresQr, 'Handing over a reward needs the customer’s code.');
        }

        // May be a cycle on another card than the one scanned, from the
        // preview's other_ready_rewards.
        $cycle = CardCycle::query()
            ->with('card.icon', 'card.merchant', 'customer')
            ->whereKey($cycleId)
            ->where('merchant_id', $merchant->id)
            ->where('customer_id', $ticket->customerId)
            ->first() ?? throw ApiException::of(ErrorCode::NotFound, 'No such cycle for this customer.');

        $redeemedAt = now();

        $handedOver = CardCycle::query()
            ->whereKey($cycle->id)
            ->where('status', CardCycleStatus::RewardReady)
            ->update(['status' => CardCycleStatus::Redeemed, 'redeemed_at' => $redeemedAt, 'updated_at' => $redeemedAt]);

        if ($handedOver === 0) {
            $cycle->refresh();

            throw $cycle->status === CardCycleStatus::Redeemed
                ? ApiException::of(ErrorCode::RewardAlreadyRedeemed, 'This reward was just handed over.', [
                    'cycle_id' => $cycle->id,
                    'redeemed_at' => $cycle->redeemed_at->toIso8601ZuluString(),
                ])
                : ApiException::of(ErrorCode::NoRewardReady, 'This cycle has no reward ready.');
        }

        $cycle->refresh();
        $cycle->customer->forceFill(['last_activity_at' => $redeemedAt])->save();

        if (! $cycle->customer->isPending()) {
            $cycle->customer->notify(new RewardRedeemed($cycle, $cycle->card));
        }

        return $cycle;
    }

    /**
     * Whether the next stamp on this card will open a new cycle: not on a
     * suspended card (decision 2).
     */
    public static function nextCycleAvailable(CardCycle $cycle): bool
    {
        return $cycle->card->status === CardStatus::Active;
    }
}
