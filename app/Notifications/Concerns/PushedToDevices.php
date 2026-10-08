<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmChannel;

/**
 * A notification kept in the in-app inbox and pushed to the account's
 * devices. The inbox entry is written at once, inside the request; the push
 * goes through the queue, after the transaction commits, so a slow or
 * unreachable FCM never holds up the cashier.
 *
 * Notifications using this implement ShouldQueueAfterCommit.
 */
trait PushedToDevices
{
    /**
     * Attempts for the push before it is given up.
     */
    public int $tries = 3;

    /**
     * Drop the push quietly when what it is about no longer exists.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * The inbox entry is written synchronously; only the push is queued.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            FcmChannel::class => config('queue.default'),
        ];
    }

    /**
     * Seconds to wait before each retry of the push.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }
}
