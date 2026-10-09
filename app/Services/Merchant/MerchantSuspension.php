<?php

namespace App\Services\Merchant;

use App\Enums\ErrorCode;
use App\Enums\MerchantStatus;
use App\Exceptions\ApiException;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionLedger;
use App\Services\Billing\SubscriptionLifecycle;
use Illuminate\Support\Facades\DB;

/**
 * Holding a shop for fraud or a breach, and letting it go (requirements §3.2,
 * transitions 7 and 8). Both are admin decisions with a written reason.
 *
 * A suspended shop stops stamping, taking customers and sending campaigns, and
 * leaves the directory, but its customers can still collect earned rewards.
 * Lifting the hold returns whatever status the dates call for now: a shop
 * whose subscription ran out meanwhile comes back EXPIRED, not ACTIVE.
 */
final class MerchantSuspension
{
    public function __construct(
        private readonly SubscriptionLedger $ledger,
        private readonly SubscriptionLifecycle $lifecycle,
        private readonly AuditLogger $audit,
    ) {}

    public function suspend(Merchant $merchant, string $reason, AdminUser $admin, ?string $ipAddress): Merchant
    {
        DB::transaction(function () use ($merchant, $reason, $admin, $ipAddress): void {
            $merchant = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);

            if ($merchant->status === null || in_array($merchant->status, [MerchantStatus::Suspended, MerchantStatus::PendingDeletion, MerchantStatus::Deleted], true)) {
                throw $this->conflict($merchant, 'Only a shop that is not already held can be suspended.');
            }

            $before = ['status' => $merchant->status->value];

            $merchant->forceFill([
                'status' => MerchantStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ])->save();

            $this->audit->record($admin, 'merchant.suspended', $merchant, $before, [
                'status' => MerchantStatus::Suspended->value,
                'reason' => $reason,
            ], $ipAddress);
        });

        return $merchant->refresh();
    }

    public function reactivate(Merchant $merchant, string $reason, AdminUser $admin, ?string $ipAddress): Merchant
    {
        DB::transaction(function () use ($merchant, $reason, $admin, $ipAddress): void {
            $merchant = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);

            if ($merchant->status !== MerchantStatus::Suspended) {
                throw $this->conflict($merchant, 'Only a suspended shop can be reactivated.');
            }

            $status = $this->ledger->statusFor($merchant);

            $merchant->forceFill([
                'status' => $status,
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();

            $this->audit->record($admin, 'merchant.reactivated', $merchant, ['status' => MerchantStatus::Suspended->value], [
                'status' => $status->value,
                'reason' => $reason,
            ], $ipAddress);
        });

        $this->lifecycle->sync($merchant->refresh());

        return $merchant->refresh();
    }

    private function conflict(Merchant $merchant, string $message): ApiException
    {
        return ApiException::of(ErrorCode::MerchantStatusConflict, $message, [
            'status' => $merchant->status?->value,
        ]);
    }
}
