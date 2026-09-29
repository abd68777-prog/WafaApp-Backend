<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\StoreStampRequest;
use App\Http\Resources\CardSummaryResource;
use App\Http\Resources\CycleProgress;
use App\Models\Merchant;
use App\Services\Stamping\ScanCustomer;
use App\Services\Stamping\StampService;
use Illuminate\Http\JsonResponse;

/**
 * Step 4 of the scan sequence: the cashier confirms, one stamp is added.
 * 201 for a new stamp, 200 with the original result for a retried request.
 */
class StampController extends Controller
{
    public function store(StoreStampRequest $request, StampService $stamps): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        ['stamp' => $stamp, 'created' => $created] = $stamps->add(
            $merchant,
            $request->string('scan_token')->value(),
            $request->string('client_uuid')->lower()->value(),
        );

        $stamp->card->loadMissing('icon');

        return response()->json([
            'data' => [
                'stamp' => [
                    'id' => $stamp->id,
                    'stamped_at' => $stamp->stamped_at->toIso8601ZuluString(),
                    'method' => $stamp->method->value,
                ],
                'cycle' => CycleProgress::of($stamp->cardCycle),
                'card' => new CardSummaryResource($stamp->card),
                'customer' => ScanCustomer::saved($stamp->customer),
            ],
        ], $created ? 201 : 200);
    }
}
