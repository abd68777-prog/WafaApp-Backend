<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Notifications\Concerns\PushedToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A notification about the customer's progress on one card (requirements
 * §7.1), kept in the in-app inbox and pushed to the customer's phones.
 *
 * The text never carries a phone number or any personal detail: it may show
 * on a locked screen someone else is looking at. The app opens the card from
 * the ids in `data`.
 */
abstract class CardNotification extends Notification implements ShouldQueueAfterCommit
{
    use PushedToDevices, Queueable;

    /**
     * A time-ordered id (UUIDv7) instead of Laravel's random one, so two
     * notifications from the same request — a stamp that also completes the
     * card — list in the order they happened.
     */
    public function __construct(
        protected CardCycle $cycle,
        protected Card $card,
    ) {
        $this->id = (string) Str::uuid7();
    }

    /**
     * The contract's NotificationType value.
     */
    abstract public function databaseType(object $notifiable): string;

    abstract protected function title(): string;

    abstract protected function body(): string;

    /**
     * @return array{title: string, body: string, data: array<string, int>}
     */
    public function toArray(Customer $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'data' => [
                'merchant_id' => $this->card->merchant_id,
                'card_id' => $this->card->id,
                'cycle_id' => $this->cycle->id,
            ],
        ];
    }

    protected function shopName(): string
    {
        return $this->card->merchant->business_name;
    }
}
