<?php

namespace App\Notifications;

/**
 * Notification 02: the card is complete and the reward is waiting.
 */
class CardCompleted extends CardNotification
{
    public function databaseType(object $notifiable): string
    {
        return 'card_completed';
    }

    protected function title(): string
    {
        return 'هديتك جاهزة';
    }

    protected function body(): string
    {
        return "هديتك جاهزة عند {$this->shopName()}! اعرض رمزك في زيارتك القادمة.";
    }
}
