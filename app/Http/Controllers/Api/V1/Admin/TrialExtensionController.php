<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ErrorCode;
use App\Enums\MerchantStatus;
use App\Enums\SubscriptionPeriodType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ExtendTrialRequest;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionLedger;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Merchant\MerchantState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Giving a shop more free days (requirements §5.3), by the Super Admin only:
 * whoever approves payments cannot also hand out time, or a friend's shop
 * could be activated for free with nothing in the records to show it.
 *
 * Only a trial is extended, for a shop that never paid. The days are added
 * to the trial's end — or to today, if it already ended, which brings an
 * expired shop back to TRIAL.
 */
class TrialExtensionController extends Controller
{
    public function store(
        ExtendTrialRequest $request,
        Merchant $merchant,
        SubscriptionLedger $ledger,
        SubscriptionLifecycle $lifecycle,
        MerchantState $state,
        AuditLogger $audit,
    ): JsonResponse {
        /** @var AdminUser $admin */
        $admin = $request->user();

        DB::transaction(function () use ($request, $merchant, $ledger, $admin, $audit): void {
            $merchant = Merchant::query()->lockForUpdate()->findOrFail($merchant->id);
            $trial = $this->extendableTrial($merchant, $ledger);
            $before = $trial->ends_at;

            $trial->forceFill([
                'ends_at' => ($before->isFuture() ? $before : now())->copy()->addDays($request->integer('days')),
            ])->save();

            $audit->record($admin, 'trial.extended', $merchant, ['trial_ends_at' => $before->toIso8601ZuluString()], [
                'trial_ends_at' => $trial->ends_at->toIso8601ZuluString(),
                'days' => $request->integer('days'),
                'reason' => $request->string('reason')->trim()->value(),
            ], $request->ip());
        });

        $lifecycle->sync($merchant->refresh());

        return response()->json(['data' => $state->subscription($merchant->refresh())]);
    }

    private function extendableTrial(Merchant $merchant, SubscriptionLedger $ledger): SubscriptionPeriod
    {
        $refuse = fn (string $reason, string $message) => ApiException::of(ErrorCode::TrialNotExtendable, $message, ['reason' => $reason]);

        if (in_array($merchant->status, [MerchantStatus::Suspended, MerchantStatus::PendingDeletion, MerchantStatus::Deleted], true) || $merchant->status === null) {
            throw $refuse('status', 'This account cannot be extended in its current status.');
        }

        if ($ledger->hasPaid($merchant)) {
            throw $refuse('paid', 'This shop has paid; only a trial can be extended.');
        }

        return $merchant->subscriptionPeriods()->where('type', SubscriptionPeriodType::Trial)->latest('ends_at')->first()
            ?? throw $refuse('no_trial', 'This shop never had a trial.');
    }
}
