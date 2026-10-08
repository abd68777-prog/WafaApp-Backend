<?php

namespace App\Services\Billing;

use App\Enums\ErrorCode;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionPeriodType;
use App\Exceptions\ApiException;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\Payment;
use App\Notifications\SubscriptionNotification;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * The payments reviewer's decision (requirements §5.2). The proof image is
 * not the evidence — the money reaching the account is — so the reviewer
 * checks the Syriatel Cash account or the transfer book first; this only
 * records the outcome.
 *
 * Each decision locks the payment, so two reviewers acting at once cannot
 * both approve, and every decision goes to the audit trail.
 */
final class PaymentReview
{
    public function __construct(
        private readonly SubscriptionLedger $ledger,
        private readonly SubscriptionLifecycle $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * A new paid period, placed by the renewal rules, and the shop back to
     * active.
     */
    public function approve(Payment $payment, AdminUser $reviewer, ?string $ipAddress): Payment
    {
        $payment = $this->decide($payment, function (Payment $payment, Merchant $merchant) use ($reviewer, $ipAddress): void {
            $placement = $this->ledger->placeNewPeriod($merchant, $payment->package, $payment->duration_months);

            $period = $merchant->subscriptionPeriods()->create([
                'package_id' => $payment->package_id,
                'type' => SubscriptionPeriodType::Paid,
                'duration_months' => $payment->duration_months,
                ...$placement,
            ]);

            $payment->forceFill([
                'status' => PaymentStatus::Approved,
                'reviewed_by_admin_id' => $reviewer->id,
                'reviewed_at' => now(),
                'subscription_period_id' => $period->id,
            ])->save();

            $this->audit->record($reviewer, 'payment.approved', $payment, ['status' => PaymentStatus::Pending->value], [
                'status' => PaymentStatus::Approved->value,
                'subscription_period_id' => $period->id,
                'starts_at' => $period->starts_at->toIso8601ZuluString(),
                'ends_at' => $period->ends_at->toIso8601ZuluString(),
            ], $ipAddress);
        });

        $merchant = $payment->merchant;
        $this->lifecycle->sync($merchant);
        $merchant->notify(SubscriptionNotification::paymentApproved($payment, $this->ledger->chainEnd($merchant)));

        return $payment;
    }

    public function reject(Payment $payment, PaymentRejectionReason $reason, AdminUser $reviewer, ?string $ipAddress): Payment
    {
        $payment = $this->decide($payment, function (Payment $payment) use ($reason, $reviewer, $ipAddress): void {
            $payment->forceFill([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by_admin_id' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->record($reviewer, 'payment.rejected', $payment, ['status' => PaymentStatus::Pending->value], [
                'status' => PaymentStatus::Rejected->value,
                'rejection_reason' => $reason->value,
            ], $ipAddress);
        });

        $payment->merchant->notify(SubscriptionNotification::paymentRejected($payment, $reason));

        return $payment;
    }

    /**
     * @param  Closure(Payment, Merchant): void  $decision
     */
    private function decide(Payment $payment, Closure $decision): Payment
    {
        return DB::transaction(function () use ($payment, $decision): Payment {
            $payment = Payment::query()->with(['package', 'merchant'])->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending) {
                throw ApiException::of(ErrorCode::PaymentNotPending, 'This payment was already reviewed.', [
                    'status' => $payment->status->value,
                ]);
            }

            $decision($payment, $payment->merchant);

            return $payment;
        });
    }
}
