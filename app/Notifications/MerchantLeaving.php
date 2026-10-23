<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Services\Merchant\MerchantState;
use Carbon\CarbonInterface;

/**
 * A shop holding the customer's earned reward has started leaving Wafa
 * (requirements §3.6): collect it before the date, while handing over is
 * still possible.
 */
class MerchantLeaving extends CardNotification
{
    public function __construct(CardCycle $cycle, Card $card, private readonly CarbonInterface $leavesAt)
    {
        parent::__construct($cycle, $card);
    }

    public function databaseType(object $notifiable): string
    {
        return 'merchant_leaving';
    }

    protected function title(): string
    {
        return "{$this->shopName()} سيغادر وفاء";
    }

    protected function body(): string
    {
        $date = $this->leavesAt->copy()->setTimezone(MerchantState::TIMEZONE)->format('Y/m/d');

        return "استلم هديتك قبل {$date}.";
    }

    /**
     * @return array{title: string, body: string, data: array<string, int|string>}
     */
    public function toArray(Customer $notifiable): array
    {
        $notification = parent::toArray($notifiable);
        $notification['data']['leaves_at'] = $this->leavesAt->toIso8601ZuluString();

        return $notification;
    }
}
