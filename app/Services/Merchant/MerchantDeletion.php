<?php

namespace App\Services\Merchant;

use App\Enums\CardCycleStatus;
use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use App\Enums\ErrorCode;
use App\Enums\MerchantStatus;
use App\Exceptions\ApiException;
use App\Models\AdminUser;
use App\Models\CardCycle;
use App\Models\DeletionRequest;
use App\Models\Merchant;
use App\Notifications\MerchantLeaving;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionLedger;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Clerk\ClerkBackendApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

/**
 * Closing a shop at its owner's request (requirements §3.6). The merchant app
 * has no delete button; the shop asks the team, which starts it here.
 *
 * Thirty days pass first and can be cancelled: stamps, new customers and
 * campaigns stop, handing over rewards goes on, and every customer holding a
 * ready reward is told to collect it. Then the sign-in, owner name, phones
 * and PIN are erased and the Clerk user is deleted. The business name, its
 * cards and the customers' rewards stay; billing records stay two years.
 */
final class MerchantDeletion
{
    public const GRACE_DAYS = 30;

    public function __construct(
        private readonly SubscriptionLedger $ledger,
        private readonly SubscriptionLifecycle $lifecycle,
        private readonly ClerkBackendApi $clerk,
        private readonly AuditLogger $audit,
    ) {}

    public function schedule(Merchant $merchant, DeletionSource $source, ?string $note, AdminUser $admin, ?string $ipAddress): DeletionRequest
    {
        $deletionRequest = DB::transaction(function () use ($merchant, $source, $note, $admin, $ipAddress): DeletionRequest {
            $merchant = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);

            if ($merchant->status === null || in_array($merchant->status, [MerchantStatus::PendingDeletion, MerchantStatus::Deleted], true)) {
                throw ApiException::of(ErrorCode::MerchantStatusConflict, 'Only a registered shop that is not already leaving can be deleted.', [
                    'status' => $merchant->status?->value,
                ]);
            }

            $deletionRequest = new DeletionRequest([
                'subject_type' => DeletionSubjectType::Merchant,
                'subject_id' => $merchant->id,
                'source' => $source,
                'requested_at' => now(),
                'executes_at' => now()->addDays(self::GRACE_DAYS),
                'note' => $note,
            ]);
            $deletionRequest->forceFill(['handled_by_admin_id' => $admin->id])->save();

            $before = ['status' => $merchant->status->value];
            $merchant->forceFill(['status' => MerchantStatus::PendingDeletion])->save();

            $this->audit->record($admin, 'merchant.deletion_scheduled', $merchant, $before, [
                'status' => MerchantStatus::PendingDeletion->value,
                'deletion_request_id' => $deletionRequest->id,
                'executes_at' => $deletionRequest->executes_at->toIso8601ZuluString(),
            ], $ipAddress);

            $this->warnCustomersWithRewards($merchant, $deletionRequest);

            return $deletionRequest;
        });

        return $deletionRequest;
    }

    public function cancel(DeletionRequest $deletionRequest, AdminUser $admin, ?string $ipAddress): DeletionRequest
    {
        $merchant = DB::transaction(function () use ($deletionRequest, $admin, $ipAddress): Merchant {
            $deletionRequest = DeletionRequest::query()->lockForUpdate()->findOrFail($deletionRequest->id);

            if (! $deletionRequest->isPending() || $deletionRequest->subject_type !== DeletionSubjectType::Merchant) {
                throw ApiException::of(ErrorCode::DeletionRequestNotPending, 'Only a shop deletion still in its waiting period can be cancelled.');
            }

            $merchant = Merchant::query()->lockForUpdate()->findOrFail($deletionRequest->subject_id);

            // A shop that was held before it asked to leave goes back to held.
            $status = $merchant->suspended_at !== null ? MerchantStatus::Suspended : $this->ledger->statusFor($merchant);

            $merchant->forceFill(['status' => $status])->save();
            $deletionRequest->forceFill(['cancelled_at' => now()])->save();

            $this->audit->record($admin, 'merchant.deletion_cancelled', $merchant, ['status' => MerchantStatus::PendingDeletion->value], [
                'status' => $status->value,
                'deletion_request_id' => $deletionRequest->id,
            ], $ipAddress);

            return $merchant;
        });

        if ($merchant->status !== MerchantStatus::Suspended) {
            $this->lifecycle->sync($merchant->refresh());
        }

        return $deletionRequest->refresh();
    }

    /**
     * Erases the shop once its waiting period is over.
     *
     * The Clerk user goes first: when Clerk cannot be reached nothing is
     * erased, and the next daily run tries again.
     *
     * @throws RequestException
     */
    public function execute(DeletionRequest $deletionRequest): void
    {
        $merchant = Merchant::query()->findOrFail($deletionRequest->subject_id);

        if ($merchant->clerk_user_id !== null) {
            $this->deleteClerkUser($merchant->clerk_user_id);
        }

        DB::transaction(function () use ($merchant, $deletionRequest): void {
            $merchant->deviceTokens()->delete();
            $merchant->notifications()->delete();

            $merchant->forceFill([
                'clerk_user_id' => null,
                'owner_name' => null,
                'email' => null,
                'phone' => null,
                'contact_phone' => null,
                'pin_hash' => null,
                'status' => MerchantStatus::Deleted,
            ])->save();

            $deletionRequest->forceFill(['executed_at' => now()])->save();

            $this->audit->record(null, 'merchant.deleted', $merchant, ['status' => MerchantStatus::PendingDeletion->value], [
                'status' => MerchantStatus::Deleted->value,
                'deletion_request_id' => $deletionRequest->id,
            ]);
        });
    }

    /**
     * Customers holding a ready reward at this shop, one notification each.
     */
    private function warnCustomersWithRewards(Merchant $merchant, DeletionRequest $deletionRequest): void
    {
        CardCycle::query()
            ->with(['card.merchant', 'customer'])
            ->where('merchant_id', $merchant->id)
            ->where('status', CardCycleStatus::RewardReady)
            ->each(function (CardCycle $cycle) use ($deletionRequest): void {
                $cycle->customer?->notify(new MerchantLeaving($cycle, $cycle->card, $deletionRequest->executes_at));
            });
    }

    /**
     * A user already removed in Clerk is what we wanted anyway.
     *
     * @throws RequestException
     */
    private function deleteClerkUser(string $clerkUserId): void
    {
        try {
            $this->clerk->deleteUser($clerkUserId);
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }
        }
    }
}
