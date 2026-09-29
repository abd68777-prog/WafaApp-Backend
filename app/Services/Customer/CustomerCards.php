<?php

namespace App\Services\Customer;

use App\Enums\CardCycleStatus;
use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The cards a customer holds, as "My cards" shows them (contract §4.3).
 *
 * A card is held once the customer has any cycle on it. Its progress is the
 * open cycle, or zero when the last reward was handed over and no stamp has
 * opened the next cycle yet (decision 1). A suspended card leaves the list
 * once its last reward is handed over, since no new cycle can start on it
 * (decision 2).
 */
final class CustomerCards
{
    /**
     * Ready rewards first, then the rest by the latest stamp.
     *
     * @return Collection<int, array{card: Card, cycle: CardCycle|null, completed_cycles_count: int, last_stamp_at: Carbon|null, merchant_muted: bool}>
     */
    public function for(Customer $customer, ?int $cardId = null): Collection
    {
        $cycles = $customer->cardCycles()
            ->with(['card.icon', 'card.merchant.businessType', 'card.merchant.governorate'])
            ->when($cardId !== null, fn ($query) => $query->where('card_id', $cardId))
            ->get()
            // A deleted shop no longer resolves; its cards go with it.
            ->filter(fn (CardCycle $cycle): bool => $cycle->card->merchant !== null);

        $lastStamps = $customer->stamps()
            ->whereNull('cancelled_at')
            ->when($cardId !== null, fn ($query) => $query->where('card_id', $cardId))
            ->selectRaw('card_id, max(stamped_at) as last_stamped_at')
            ->groupBy('card_id')
            ->pluck('last_stamped_at', 'card_id');

        $mutedMerchants = $customer->merchantMutes()->pluck('merchant_id')->flip();

        return $cycles
            ->groupBy('card_id')
            ->map(function (Collection $cardCycles) use ($lastStamps, $mutedMerchants): ?array {
                /** @var CardCycle|null $open */
                $open = $cardCycles->first(fn (CardCycle $cycle): bool => $cycle->status !== CardCycleStatus::Redeemed);
                $card = $cardCycles->first()->card;

                if ($open === null && $card->status === CardStatus::Suspended) {
                    return null;
                }

                $lastStampedAt = $lastStamps[$card->id] ?? null;

                return [
                    'card' => $card,
                    'cycle' => $open,
                    'completed_cycles_count' => $cardCycles->where('status', CardCycleStatus::Redeemed)->count(),
                    'last_stamp_at' => $lastStampedAt !== null ? Carbon::parse($lastStampedAt) : null,
                    'merchant_muted' => $mutedMerchants->has($card->merchant_id),
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $a, array $b): int => $this->isReady($b) <=> $this->isReady($a),
                fn (array $a, array $b): int => ($b['last_stamp_at']?->getTimestamp() ?? 0) <=> ($a['last_stamp_at']?->getTimestamp() ?? 0),
            ])
            ->values();
    }

    /**
     * @param  array{cycle: CardCycle|null}  $entry
     */
    private function isReady(array $entry): bool
    {
        return $entry['cycle']?->status === CardCycleStatus::RewardReady;
    }
}
