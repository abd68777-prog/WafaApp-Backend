<?php

namespace App\Services\Stamping;

use App\Enums\CardCycleStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Stamp;
use App\Notifications\CardCompleted;
use App\Notifications\StampAdded;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Adds one stamp, confirmed from the scan preview (contract merchantAddStamp).
 *
 * One stamp per request, with no quantity: a cashier who could add several at
 * once could fill a friend's card in one tap. The app generates `client_uuid`
 * once per tap of "confirm", so a retry after a slow network or a double tap
 * returns the original stamp instead of adding a second one.
 */
final class StampService
{
    public function __construct(
        private readonly ScanToken $scanToken,
        private readonly StampRules $rules,
    ) {}

    /**
     * @return array{stamp: Stamp, created: bool}
     */
    public function add(Merchant $merchant, string $scanToken, string $clientUuid): array
    {
        if ($existing = $this->replay($merchant, $clientUuid)) {
            return ['stamp' => $existing, 'created' => false];
        }

        $ticket = $this->scanToken->read($merchant, $scanToken);

        try {
            $stamp = DB::transaction(fn (): Stamp => $this->addStamp($merchant, $ticket, $clientUuid), attempts: 3);
        } catch (UniqueConstraintViolationException) {
            // Either the same tap raced itself past the replay check above, or
            // two devices created the same pending customer or cycle at once;
            // the second attempt finds the rows the first one committed.
            if ($existing = $this->replay($merchant, $clientUuid)) {
                return ['stamp' => $existing, 'created' => false];
            }

            $stamp = DB::transaction(fn (): Stamp => $this->addStamp($merchant, $ticket, $clientUuid), attempts: 3);
        }

        $this->notify($stamp);

        return ['stamp' => $stamp, 'created' => true];
    }

    /**
     * Everything is checked again inside the transaction, with the customer's
     * row locked: two devices confirming the same customer at once queue up
     * here, and the second one sees the first one's stamp.
     */
    private function addStamp(Merchant $merchant, ScanTicket $ticket, string $clientUuid): Stamp
    {
        $card = $merchant->cards()->find($ticket->cardId) ?? throw $this->scanExpired();
        $customer = $this->lockCustomer($ticket);

        $cycle = $card->cycles()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', CardCycleStatus::Redeemed)
            ->first();

        if ($cycle?->status === CardCycleStatus::RewardReady) {
            throw ApiException::of(ErrorCode::RewardReadyRedeemFirst, 'The card is complete; hand over the reward first.', [
                'cycle_id' => $cycle->id,
            ]);
        }

        if ($blocker = $this->rules->blocker($merchant, $card, $customer->id, $cycle)) {
            throw $blocker;
        }

        // The next cycle opens with the next stamp, not when the reward is
        // handed over (decision 1).
        $cycle ??= $this->openCycle($card, $customer);

        $stamp = new Stamp([
            'card_cycle_id' => $cycle->id,
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'method' => $ticket->method,
            'client_uuid' => $clientUuid,
            'stamped_at' => now(),
        ]);
        $stamp->save();

        $cycle->stamps_count++;

        if ($cycle->stamps_count >= $card->stamps_required) {
            $cycle->forceFill(['status' => CardCycleStatus::RewardReady, 'completed_at' => now()]);
        }

        $cycle->save();
        $customer->forceFill(['last_activity_at' => now()])->save();

        return $stamp->setRelation('cardCycle', $cycle)->setRelation('card', $card)->setRelation('customer', $customer);
    }

    /**
     * The customer the scan found, or — for a number nobody registered — a
     * pending customer created now (decision 4). Locked for the rest of the
     * transaction.
     */
    private function lockCustomer(ScanTicket $ticket): Customer
    {
        if ($ticket->customerId !== null) {
            return Customer::query()->whereKey($ticket->customerId)->lockForUpdate()->first() ?? throw $this->scanExpired();
        }

        $existing = Customer::query()->where('phone', $ticket->phone)->lockForUpdate()->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = new Customer(['phone' => $ticket->phone]);
        $customer->forceFill(['last_activity_at' => now()])->save();

        return Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
    }

    private function openCycle(Card $card, Customer $customer): CardCycle
    {
        $cycle = new CardCycle([
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
        ]);
        $cycle->forceFill(['stamps_count' => 0, 'status' => CardCycleStatus::Collecting])->save();

        return $cycle;
    }

    /**
     * The stamp an earlier request with this `client_uuid` added. A uuid used
     * by another shop is not theirs to read back.
     */
    private function replay(Merchant $merchant, string $clientUuid): ?Stamp
    {
        $stamp = Stamp::query()->with(['cardCycle', 'card', 'customer'])->where('client_uuid', $clientUuid)->first();

        if ($stamp !== null && $stamp->merchant_id !== $merchant->id) {
            throw ApiException::of(ErrorCode::ValidationFailed, 'This client_uuid was already used.', [
                'fields' => ['client_uuid' => ['taken']],
            ]);
        }

        return $stamp;
    }

    /**
     * The customer hears about every stamp: for a stamp added by phone number
     * while they were away, this is the protection agreed for that path
     * (requirements §7.1). A pending customer has no app to notify.
     */
    private function notify(Stamp $stamp): void
    {
        $customer = $stamp->customer;

        if ($customer->isPending()) {
            return;
        }

        $customer->notify(new StampAdded($stamp->cardCycle, $stamp->card));

        if ($stamp->cardCycle->status === CardCycleStatus::RewardReady) {
            $customer->notify(new CardCompleted($stamp->cardCycle, $stamp->card));
        }
    }

    private function scanExpired(): ApiException
    {
        return ApiException::of(ErrorCode::ScanTokenExpired, 'The scan is no longer valid; scan the customer again.');
    }
}
