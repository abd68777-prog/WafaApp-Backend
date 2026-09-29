<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerCardResource;
use App\Models\Customer;
use App\Models\Stamp;
use App\Services\Customer\CustomerCards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * "My cards" in the customer app (contract §4.3).
 */
class CardController extends Controller
{
    public function __construct(private readonly CustomerCards $cards) {}

    /**
     * Every card at once, without pages: a customer holds a handful.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return CustomerCardResource::collection($this->cards->for($customer));
    }

    /**
     * One card with the dates of the stamps in the current cycle.
     */
    public function show(Request $request, int $card): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $entry = $this->cards->for($customer, $card)->first();

        abort_if($entry === null, 404);

        $stamps = $entry['cycle']?->stamps()
            ->whereNull('cancelled_at')
            ->orderBy('stamped_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Stamp $stamp): array => [
                'stamped_at' => $stamp->stamped_at->toIso8601ZuluString(),
                'method' => $stamp->method->value,
            ])
            ->all() ?? [];

        return response()->json([
            'data' => [
                ...(new CustomerCardResource($entry))->toArray($request),
                'stamps' => $stamps,
                'merchant_address' => $entry['card']->merchant->address,
            ],
        ]);
    }
}
