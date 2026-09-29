<?php

namespace App\Services\Stamping;

use App\Enums\CardCycleStatus;
use App\Enums\ErrorCode;
use App\Enums\StampMethod;
use App\Exceptions\ApiException;
use App\Http\Resources\CardSummaryResource;
use App\Http\Resources\CycleProgress;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Services\Customer\CustomerQrCode;

/**
 * The confirmation screen after a scan or a typed number (contract
 * merchantResolveScan). Writes nothing: the pending customer for a new number
 * is created only when the stamp is confirmed.
 *
 * Errors that stop us from knowing the customer are thrown. Once the customer
 * is known the answer is always a preview, with the reason a stamp is not
 * possible in `blocked_reason` — the same screen may still offer a reward
 * ready on another card.
 */
final class ScanPreview
{
    public function __construct(
        private readonly CustomerQrCode $qrCode,
        private readonly ScanToken $scanToken,
        private readonly StampRules $rules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Merchant $merchant, int $cardId, ?string $qr, ?string $phone): array
    {
        $card = $merchant->cards()->with('icon')->find($cardId)
            ?? throw ApiException::of(ErrorCode::NotFound, 'This card does not belong to the shop.');

        $method = $qr !== null ? StampMethod::Qr : StampMethod::Phone;
        $customer = $qr !== null
            ? $this->qrCode->resolve($qr)
            : Customer::query()->where('phone', $phone)->first();

        $openCycle = $customer !== null ? $this->openCycle($card, $customer) : null;
        [$action, $blockedReason] = $this->action($merchant, $card, $customer, $openCycle, $method);

        $ticket = ScanToken::ticket($merchant, $card->id, $customer?->id, $customer === null ? $phone : null, $method);

        return [
            'scan_token' => $this->scanToken->issue($ticket),
            'expires_at' => $ticket->expiresAt->toIso8601ZuluString(),
            'method' => $method->value,
            'customer' => ScanCustomer::preview($customer, $phone),
            'card' => new CardSummaryResource($card),
            'cycle' => CycleProgress::of($openCycle),
            'action' => $action,
            'blocked_reason' => $blockedReason?->toErrorBody(),
            'other_ready_rewards' => $customer !== null ? $this->otherReadyRewards($merchant, $card, $customer) : [],
        ];
    }

    /**
     * The one button on the confirmation screen.
     *
     * @return array{'stamp'|'redeem'|'none', ApiException|null}
     */
    private function action(Merchant $merchant, Card $card, ?Customer $customer, ?CardCycle $openCycle, StampMethod $method): array
    {
        if ($openCycle?->status === CardCycleStatus::RewardReady) {
            // Handing over a reward needs the customer present with their
            // code (decision 5), never a typed number.
            return $method === StampMethod::Qr
                ? ['redeem', null]
                : ['none', ApiException::of(ErrorCode::RedeemRequiresQr, 'Handing over a reward needs the customer’s code.')];
        }

        $blocker = $this->rules->blocker($merchant, $card, $customer?->id, $openCycle);

        return $blocker === null ? ['stamp', null] : ['none', $blocker];
    }

    private function openCycle(Card $card, Customer $customer): ?CardCycle
    {
        return $card->cycles()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', CardCycleStatus::Redeemed)
            ->first();
    }

    /**
     * Rewards waiting for this customer on the shop's other cards, so the
     * cashier can hand them over in the same visit.
     *
     * @return list<array{cycle_id: int, card: CardSummaryResource, completed_at: string}>
     */
    private function otherReadyRewards(Merchant $merchant, Card $card, Customer $customer): array
    {
        return $merchant->cardCycles()
            ->with('card.icon')
            ->where('customer_id', $customer->id)
            ->where('card_id', '!=', $card->id)
            ->where('status', CardCycleStatus::RewardReady)
            ->orderBy('completed_at')
            ->get()
            ->map(fn (CardCycle $cycle): array => [
                'cycle_id' => $cycle->id,
                'card' => new CardSummaryResource($cycle->card),
                'completed_at' => $cycle->completed_at->toIso8601ZuluString(),
            ])
            ->all();
    }
}
