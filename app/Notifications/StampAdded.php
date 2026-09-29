<?php

namespace App\Notifications;

/**
 * Notification 01: a stamp was added.
 */
class StampAdded extends CardNotification
{
    public function databaseType(object $notifiable): string
    {
        return 'stamp_added';
    }

    protected function title(): string
    {
        return 'طابع جديد';
    }

    protected function body(): string
    {
        return "أُضيف لك طابع عند {$this->shopName()}. صار لديك {$this->cycle->stamps_count} من {$this->card->stamps_required}.";
    }
}
