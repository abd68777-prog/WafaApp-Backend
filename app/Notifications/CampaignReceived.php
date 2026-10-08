<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\Customer;
use App\Notifications\Concerns\PushedToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A shop's campaign (requirements §7.3): the merchant's title and text,
 * with the shop's name in front so a locked screen says who it is from.
 */
class CampaignReceived extends Notification implements ShouldQueueAfterCommit
{
    use PushedToDevices, Queueable;

    private readonly string $title;

    private readonly string $body;

    /**
     * @var array{merchant_id: int, campaign_id: int}
     */
    private readonly array $data;

    public function __construct(Campaign $campaign, string $businessName)
    {
        $this->id = (string) Str::uuid7();
        $this->title = "{$businessName}: {$campaign->title}";
        $this->body = $campaign->body;
        $this->data = ['merchant_id' => $campaign->merchant_id, 'campaign_id' => $campaign->id];
    }

    public function databaseType(object $notifiable): string
    {
        return 'campaign';
    }

    /**
     * @return array{title: string, body: string, data: array{merchant_id: int, campaign_id: int}}
     */
    public function toArray(Customer $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'data' => $this->data];
    }
}
