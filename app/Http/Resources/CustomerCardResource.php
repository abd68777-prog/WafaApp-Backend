<?php

namespace App\Http\Resources;

use App\Models\Card;
use App\Models\CardCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One card in "My cards" (contract CustomerCard), built from an entry of
 * CustomerCards.
 *
 * @property-read array{card: Card, cycle: CardCycle|null, completed_cycles_count: int, last_stamp_at: Carbon|null, merchant_muted: bool} $resource
 */
class CustomerCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'card' => new CardSummaryResource($this->resource['card']),
            'merchant' => new MerchantSummaryResource($this->resource['card']->merchant),
            'cycle' => CycleProgress::of($this->resource['cycle']),
            'completed_cycles_count' => $this->resource['completed_cycles_count'],
            'last_stamp_at' => $this->resource['last_stamp_at']?->toIso8601ZuluString(),
            'merchant_muted' => $this->resource['merchant_muted'],
        ];
    }
}
