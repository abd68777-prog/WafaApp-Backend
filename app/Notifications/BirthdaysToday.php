<?php

namespace App\Notifications;

use App\Models\Merchant;
use App\Notifications\Concerns\PushedToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The merchant's morning reminder that some customers have their birthday
 * today, so the greeting is not forgotten. The names stay in the app, behind
 * the PIN: the text only says how many.
 */
class BirthdaysToday extends Notification implements ShouldQueueAfterCommit
{
    use PushedToDevices, Queueable;

    public function __construct(private readonly int $count)
    {
        $this->id = (string) Str::uuid7();
    }

    public function databaseType(object $notifiable): string
    {
        return 'birthdays_today';
    }

    /**
     * @return array{title: string, body: string, data: array{count: int}}
     */
    public function toArray(Merchant $notifiable): array
    {
        return [
            'title' => 'أعياد ميلاد اليوم',
            'body' => $this->count === 1
                ? 'اليوم عيد ميلاد أحد زبائنك. هنّئه من التطبيق.'
                : "اليوم عيد ميلاد {$this->count} من زبائنك. هنّئهم من التطبيق.",
            'data' => ['count' => $this->count],
        ];
    }
}
