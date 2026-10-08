<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPeriodResource;
use App\Models\Merchant;
use App\Services\Merchant\MerchantState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The subscription tab (contract §5.9): what `me` says about the
 * subscription, plus every period so far, the newest first — including a
 * renewal that has not started yet.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request, MerchantState $state): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        return response()->json([
            'data' => [
                ...$state->subscription($merchant),
                'periods' => SubscriptionPeriodResource::collection(
                    $merchant->subscriptionPeriods()->with('package')->orderByDesc('starts_at')->orderByDesc('id')->get()
                ),
            ],
        ]);
    }
}
