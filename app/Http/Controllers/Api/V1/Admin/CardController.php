<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminActionReasonRequest;
use App\Models\AdminUser;
use App\Models\Card;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Taking down a card with unsuitable content (requirements §5.3). It is
 * suspended exactly as when the merchant does it: no new stamps or customers,
 * while rewards already earned can still be collected. Suspending a card that
 * is already suspended changes nothing and records nothing.
 */
class CardController extends Controller
{
    public function suspend(AdminActionReasonRequest $request, Card $card, AuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        DB::transaction(function () use ($request, $card, $admin, $audit): void {
            $card = Card::query()->lockForUpdate()->findOrFail($card->id);

            if ($card->status === CardStatus::Suspended) {
                return;
            }

            $card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();

            $audit->record($admin, 'card.suspended', $card, ['status' => CardStatus::Active->value], [
                'status' => CardStatus::Suspended->value,
                'reason' => $request->reason(),
            ], $request->ip());
        });

        $card->refresh();

        return response()->json([
            'data' => [
                'id' => $card->id,
                'merchant_id' => $card->merchant_id,
                'name' => $card->name,
                'status' => $card->status->value,
                'suspended_at' => $card->suspended_at?->toIso8601ZuluString(),
            ],
        ]);
    }
}
