<?php

namespace App\Services\Stamping;

use App\Enums\CardStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\Stamp;
use Carbon\CarbonImmutable;

/**
 * The rules a stamp must pass (requirements §2.3), shared by the preview and
 * the confirmation: the preview shows the reason on the confirmation screen,
 * and the confirmation checks again because another device may have stamped
 * the same customer in between.
 *
 * A completed card is not a rule here: the preview offers the reward
 * instead, and the confirmation refuses with REWARD_READY_REDEEM_FIRST.
 */
final class StampRules
{
    /**
     * Why this stamp cannot be added now, or null when it can.
     *
     * @param  int|null  $customerId  null for a number nobody registered yet
     * @param  CardCycle|null  $openCycle  the customer's unfinished cycle on the card
     */
    public function blocker(Merchant $merchant, Card $card, ?int $customerId, ?CardCycle $openCycle): ?ApiException
    {
        if (! $merchant->status->canCollectStamps()) {
            return ApiException::of(ErrorCode::MerchantStatusBlocksAction, 'The subscription does not allow stamps now.', [
                'status' => $merchant->status->value,
                'action' => 'stamps',
            ]);
        }

        // A suspended card lets customers finish the cycle they are on, but
        // starts no new one: neither for a newcomer nor after a reward.
        if ($openCycle === null && $card->status === CardStatus::Suspended) {
            return ApiException::of(ErrorCode::CardSuspended, 'This card takes no new customers or cycles.');
        }

        return $customerId !== null ? $this->intervalBlocker($card, $customerId) : null;
    }

    /**
     * One stamp per customer per card within the configured interval, by QR
     * and by phone alike, so a friend cannot fill a card in one visit.
     */
    private function intervalBlocker(Card $card, int $customerId): ?ApiException
    {
        $intervalMinutes = (int) Setting::read('stamp_interval_minutes', 60);

        if ($intervalMinutes <= 0) {
            return null;
        }

        $lastStampedAt = Stamp::query()
            ->where('card_id', $card->id)
            ->where('customer_id', $customerId)
            ->whereNull('cancelled_at')
            ->max('stamped_at');

        if ($lastStampedAt === null) {
            return null;
        }

        $lastStampedAt = CarbonImmutable::parse($lastStampedAt);
        $nextAllowedAt = $lastStampedAt->addMinutes($intervalMinutes);

        if ($nextAllowedAt->lessThanOrEqualTo(now())) {
            return null;
        }

        return ApiException::of(ErrorCode::StampInterval, 'Stamp interval not elapsed.', [
            'last_stamp_at' => $lastStampedAt->toIso8601ZuluString(),
            'next_allowed_at' => $nextAllowedAt->toIso8601ZuluString(),
            'minutes_since_last' => (int) floor($lastStampedAt->diffInMinutes(now())),
            'minutes_remaining' => (int) ceil(now()->diffInSeconds($nextAllowedAt) / 60),
        ]);
    }
}
