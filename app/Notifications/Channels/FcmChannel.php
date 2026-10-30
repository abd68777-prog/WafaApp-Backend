<?php

namespace App\Notifications\Channels;

use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Merchant;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as PushNotification;

/**
 * Push delivery through Firebase Cloud Messaging, to every device the
 * account is signed in on. FCM reaches iOS through APNs on its own, so one
 * code path serves both apps on both platforms.
 *
 * The push repeats the inbox entry the same notification has just written —
 * same title, body and ids — so a tap opens exactly what the inbox shows.
 * It also carries the inbox's unread count for the badge on the app icon,
 * which the system draws from the push alone while the app is closed.
 * Without Firebase credentials (local development, tests) nothing is sent.
 */
class FcmChannel
{
    /**
     * Whether a Firebase service account is configured.
     */
    public static function isConfigured(): bool
    {
        return filled(config('firebase.projects.'.config('firebase.default').'.credentials'));
    }

    /**
     * The message for one inbox entry. FCM only carries strings in `data`.
     * `$unread` is the badge: `aps.badge` on iOS, `notification_count` on
     * Android (most launchers show a dot, Samsung shows the number).
     */
    public static function messageFor(DatabaseNotification $entry, int $unread): CloudMessage
    {
        $data = ['type' => $entry->type, 'notification_id' => $entry->id];

        foreach ($entry->data['data'] ?? [] as $key => $value) {
            $data[$key] = (string) $value;
        }

        // Set before the priority and sounds, which add to these configs.
        return CloudMessage::new()
            ->withNotification(PushNotification::create($entry->data['title'], $entry->data['body']))
            ->withData($data)
            ->withApnsConfig(ApnsConfig::new()->withBadge($unread))
            ->withAndroidConfig(AndroidConfig::fromArray(['notification' => ['notification_count' => $unread]]))
            ->withHighestPossiblePriority()
            ->withDefaultSounds();
    }

    public function send(Customer|Merchant $notifiable, Notification $notification): void
    {
        if (! self::isConfigured()) {
            Log::debug('FCM is not configured; push skipped.', ['notification_id' => $notification->id]);

            return;
        }

        // Gone when the account was deleted before the queue got here.
        $entry = $notifiable->notifications()->find($notification->id);
        $tokens = $notifiable->deviceTokens()->pluck('token')->all();

        if ($entry === null || $tokens === []) {
            return;
        }

        // Counted now, so it includes this entry and leaves out anything read
        // while the push waited in the queue.
        $unread = $notifiable->unreadNotifications()->count();

        $report = app(Messaging::class)->sendMulticast(self::messageFor($entry, $unread), $tokens);

        // Uninstalled apps and tokens FCM no longer recognises.
        $dead = [...$report->unknownTokens(), ...$report->invalidTokens()];

        if ($dead !== []) {
            DeviceToken::query()->whereIn('token', $dead)->delete();
        }
    }
}
