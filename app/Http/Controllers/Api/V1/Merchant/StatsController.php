<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Merchant\MerchantStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The statistics tab (contract §5.5), for the whole shop or one card.
 */
class StatsController extends Controller
{
    public function show(Request $request, MerchantStatistics $statistics): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $validated = $request->validate([
            'card_id' => ['sometimes', 'integer'],
            'days' => ['sometimes', 'integer', Rule::in([7, 30])],
        ]);

        $card = isset($validated['card_id'])
            ? $merchant->cards()->findOrFail($validated['card_id'], ['id', 'name', 'status'])
            : null;

        return response()->json([
            'data' => $statistics->of($merchant, $card, (int) ($validated['days'] ?? 7)),
        ]);
    }
}
