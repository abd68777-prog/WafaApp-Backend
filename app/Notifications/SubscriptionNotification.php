<?php

namespace App\Notifications;

use App\Enums\PaymentRejectionReason;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use App\Notifications\Concerns\PushedToDevices;
use App\Services\Merchant\MerchantState;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The merchant's subscription and payment notifications (requirements §7.2),
 * in the inbox and as a push. The text is fixed when the event happens, so a
 * push sent from the queue a moment later says exactly what the inbox says.
 */
class SubscriptionNotification extends Notification implements ShouldQueueAfterCommit
{
    use PushedToDevices, Queueable;

    /**
     * @param  array<string, int|string>  $data
     */
    private function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly string $body,
        private readonly array $data,
    ) {
        $this->id = (string) Str::uuid7();
    }

    public static function trialEnding(SubscriptionPeriod $period, CarbonInterface $endsAt, int $days): self
    {
        return new self('trial_ending', 'تجربتك تقترب من نهايتها', 'تنتهي تجربتك المجانية بعد '.self::days($days).'. اشترك لتكمل.', [
            'period_id' => $period->id,
            'key' => self::reminderKey('trial_ending', $period, $endsAt),
        ]);
    }

    public static function subscriptionEnding(SubscriptionPeriod $period, CarbonInterface $endsAt, int $days): self
    {
        return new self('subscription_ending', 'اشتراكك يقترب من نهايته', 'ينتهي اشتراكك بعد '.self::days($days).'.', [
            'period_id' => $period->id,
            'key' => self::reminderKey('subscription_ending', $period, $endsAt),
        ]);
    }

    public static function graceStarted(SubscriptionPeriod $period, int $days): self
    {
        return new self('grace_started', 'مهلة السماح', 'انتهى اشتراكك، ولديك مهلة '.self::days($days).' قبل توقف الطوابع.', [
            'period_id' => $period->id,
        ]);
    }

    public static function subscriptionExpired(): self
    {
        return new self('subscription_expired', 'انتهى اشتراكك', 'اشتراكك منتهٍ والطوابع متوقفة. تسليم الهدايا ما زال متاحاً.', []);
    }

    public static function paymentApproved(Payment $payment, CarbonInterface $activeUntil): self
    {
        $date = $activeUntil->copy()->setTimezone(MerchantState::TIMEZONE)->format('Y/m/d');

        return new self('payment_approved', 'قُبلت دفعتك', "قُبلت دفعتك، واشتراكك فعّال حتى {$date}.", [
            'payment_id' => $payment->id,
        ]);
    }

    public static function paymentRejected(Payment $payment, PaymentRejectionReason $reason): self
    {
        return new self('payment_rejected', 'لم تُقبل دفعتك', "لم تُقبل دفعتك: {$reason->label()}.", [
            'payment_id' => $payment->id,
            'reason' => $reason->value,
        ]);
    }

    /**
     * A reminder is sent once per end date: extending a trial moves the date,
     * and the reminder comes again before the new one.
     */
    public static function reminderKey(string $type, SubscriptionPeriod $period, CarbonInterface $endsAt): string
    {
        return "{$type}:{$period->id}:{$endsAt->getTimestamp()}";
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type;
    }

    /**
     * @return array{title: string, body: string, data: array<string, int|string>}
     */
    public function toArray(Merchant $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'data' => $this->data];
    }

    /**
     * «يوم واحد», «يومين», «3 أيام», «11 يوماً».
     */
    private static function days(int $days): string
    {
        return match (true) {
            $days === 1 => 'يوم واحد',
            $days === 2 => 'يومين',
            $days <= 10 => "{$days} أيام",
            default => "{$days} يوماً",
        };
    }
}
