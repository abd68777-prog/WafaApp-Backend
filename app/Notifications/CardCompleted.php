<?php

namespace App\Notifications;

/**
 * Notification 02: the card is complete and the reward is waiting.
 *
 * Worded for both cases: the cashier may hand the reward over in the same
 * visit, right after the last stamp, or on a later one.
 */
class CardCompleted extends CardNotification
{
    public function databaseType(object $notifiable): string
    {
        return 'card_completed';
    }

    protected function title(): string
    {
        return 'هديتك جاهزة!';
    }

    protected function body(): string
    {
        return "اكتملت بطاقتك في {$this->shopName()}. اعرض رمزك للكاشير لتستلم {$this->card->reward_description}.";
    }
}
