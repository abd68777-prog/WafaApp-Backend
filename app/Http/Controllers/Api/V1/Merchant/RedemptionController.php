<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\StoreRedemptionRequest;
use App\Http\Resources\CardSummaryResource;
use App\Models\Merchant;
use App\Services\Stamping\RedemptionService;
use Illuminate\Http\JsonResponse;

/**
 * Step 5 of the scan sequence: the reward is handed over.
 */
class RedemptionController extends Controller
{
    public function store(StoreRedemptionRequest $request, RedemptionService $redemptions): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $cycle = $redemptions->redeem($merchant, $request->string('scan_token')->value(), $request->integer('cycle_id'));

        return response()->json([
            'data' => [
                'cycle_id' => $cycle->id,
                'redeemed_at' => $cycle->redeemed_at->toIso8601ZuluString(),
                'card' => new CardSummaryResource($cycle->card),
                'next_cycle_available' => RedemptionService::nextCycleAvailable($cycle),
            ],
        ]);
    }
}
