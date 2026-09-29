<?php

namespace App\Notifications;

use App\Enums\CardStatus;

/**
 * Notification 03: the reward was handed over. A new card starts only where
 * the card is still active (decision 2).
 */
class RewardRedeemed extends CardNotification
{
    public function databaseType(object $notifiable): string
    {
        return 'reward_redeemed';
    }

    protected function title(): string
    {
        return 'استلمت هديتك';
    }

    protected function body(): string
    {
        return $this->card->status === CardStatus::Active
            ? "استلمت هديتك من {$this->shopName()}. بدأت بطاقتك الجديدة."
            : "استلمت هديتك من {$this->shopName()}.";
    }
}
