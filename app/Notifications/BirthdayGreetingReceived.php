<?php

namespace App\Notifications;

use App\Models\BirthdayGreeting;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A shop's birthday greeting, in the merchant's own words, with the gift on
 * a line of its own when there is one.
 *
 * Unlike campaigns it reaches every customer, including those who muted the
 * shop or all offers: it is one message a year to one person, not an
 * advertisement.
 */
class BirthdayGreetingReceived extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Merchant $merchant,
        private readonly BirthdayGreeting $greeting,
    ) {
        $this->id = (string) Str::uuid7();
    }

    public function databaseType(object $notifiable): string
    {
        return 'birthday_greeting';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(Customer $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, data: array<string, int>}
     */
    public function toArray(Customer $notifiable): array
    {
        $body = $this->greeting->message;

        if (filled($this->greeting->gift)) {
            $body .= "\nهديتك: {$this->greeting->gift}";
        }

        return [
            'title' => "عيد ميلاد سعيد من {$this->merchant->business_name}",
            'body' => $body,
            'data' => ['merchant_id' => $this->merchant->id],
        ];
    }
}
