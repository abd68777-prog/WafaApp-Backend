<?php

namespace App\Console\Commands;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Sends one push straight to a device token, without the queue, to check
 * the Firebase credentials and a phone's setup end to end.
 */
#[Signature('push:test {token : The FCM token the app registered}')]
#[Description('Send a test push notification to one device token')]
class PushTest extends Command
{
    public function handle(): int
    {
        if (! FcmChannel::isConfigured()) {
            $this->error('Firebase is not configured: set FIREBASE_CREDENTIALS to the service account JSON file.');

            return self::FAILURE;
        }

        $message = CloudMessage::new()
            ->withToken($this->argument('token'))
            ->withNotification(Notification::create('وفاء', 'إشعار تجريبي من الخادم.'))
            ->withData(['type' => 'test'])
            ->withHighestPossiblePriority()
            ->withDefaultSounds();

        try {
            $result = app(Messaging::class)->send($message);
        } catch (FirebaseException $exception) {
            $this->error('FCM refused the push: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent: '.($result['name'] ?? 'ok'));

        return self::SUCCESS;
    }
}
