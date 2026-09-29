<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CardCycleStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CancelStampRequest;
use App\Http\Resources\CycleProgress;
use App\Models\AdminUser;
use App\Models\CardCycle;
use App\Models\Stamp;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Correcting a wrong stamp (requirements §2.3). The merchant app has no undo:
 * the merchant calls the platform, and an admin cancels the stamp here with a
 * written reason.
 */
class StampController extends Controller
{
    /**
     * The stamp is marked, never deleted, so a dispute can be read later. A
     * card completed by this stamp goes back to collecting. Nothing can be
     * cancelled once the reward was handed over: it already left the shop.
     */
    public function cancel(CancelStampRequest $request, Stamp $stamp, AuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $stamp = DB::transaction(function () use ($stamp, $admin, $request, $audit): Stamp {
            $cycle = CardCycle::query()->with('card')->lockForUpdate()->findOrFail($stamp->card_cycle_id);
            $stamp->refresh();

            if ($stamp->isCancelled()) {
                return $stamp->setRelation('cardCycle', $cycle);
            }

            if ($cycle->status === CardCycleStatus::Redeemed) {
                throw ApiException::of(ErrorCode::RewardAlreadyRedeemed, 'The reward of this cycle was handed over; its stamps stay.', [
                    'cycle_id' => $cycle->id,
                    'redeemed_at' => $cycle->redeemed_at->toIso8601ZuluString(),
                ]);
            }

            $before = ['stamps_count' => $cycle->stamps_count, 'status' => $cycle->status->value];

            $stamp->forceFill([
                'cancelled_at' => now(),
                'cancel_reason' => $request->string('reason')->trim()->value(),
                'cancelled_by_admin_id' => $admin->id,
            ])->save();

            $cycle->stamps_count = max(0, $cycle->stamps_count - 1);

            if ($cycle->status === CardCycleStatus::RewardReady && $cycle->stamps_count < $cycle->card->stamps_required) {
                $cycle->forceFill(['status' => CardCycleStatus::Collecting, 'completed_at' => null]);
            }

            $cycle->save();

            $audit->record($admin, 'stamp.cancelled', $stamp, $before, [
                'stamps_count' => $cycle->stamps_count,
                'status' => $cycle->status->value,
                'reason' => $stamp->cancel_reason,
            ], $request->ip());

            return $stamp->setRelation('cardCycle', $cycle);
        });

        return response()->json([
            'data' => [
                'id' => $stamp->id,
                'cancelled_at' => $stamp->cancelled_at->toIso8601ZuluString(),
                'cancel_reason' => $stamp->cancel_reason,
                'cycle' => CycleProgress::of($stamp->cardCycle),
            ],
        ]);
    }
}
