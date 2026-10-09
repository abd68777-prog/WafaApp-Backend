<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardCycle;
use App\Models\Stamp;
use Illuminate\Http\JsonResponse;

/**
 * One cycle with every stamp in it, cancelled ones included (requirements
 * §5.4): where a wrong stamp is found and cancelled from.
 */
class CardCycleController extends Controller
{
    public function show(CardCycle $cycle): JsonResponse
    {
        $cycle->load([
            'card:id,name,stamps_required',
            'merchant' => fn ($merchant) => $merchant->withTrashed()->select('id', 'business_name'),
            'stamps' => fn ($stamps) => $stamps->with('cancelledBy:id,name')->orderBy('stamped_at')->orderBy('id'),
        ]);

        return response()->json([
            'data' => [
                'id' => $cycle->id,
                'customer_id' => $cycle->customer_id,
                'merchant' => ['id' => $cycle->merchant->id, 'business_name' => $cycle->merchant->business_name],
                'card' => ['id' => $cycle->card->id, 'name' => $cycle->card->name],
                'stamps_count' => $cycle->stamps_count,
                'stamps_required' => $cycle->card->stamps_required,
                'status' => $cycle->status->value,
                'started_at' => $cycle->created_at->toIso8601ZuluString(),
                'completed_at' => $cycle->completed_at?->toIso8601ZuluString(),
                'redeemed_at' => $cycle->redeemed_at?->toIso8601ZuluString(),
                'stamps' => $cycle->stamps->map(fn (Stamp $stamp): array => [
                    'id' => $stamp->id,
                    'method' => $stamp->method->value,
                    'stamped_at' => $stamp->stamped_at->toIso8601ZuluString(),
                    'cancelled_at' => $stamp->cancelled_at?->toIso8601ZuluString(),
                    'cancel_reason' => $stamp->cancel_reason,
                    'cancelled_by' => $stamp->cancelledBy === null ? null : ['id' => $stamp->cancelledBy->id, 'name' => $stamp->cancelledBy->name],
                ])->all(),
            ],
        ]);
    }
}
