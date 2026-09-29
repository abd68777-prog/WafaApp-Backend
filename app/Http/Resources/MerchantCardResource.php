<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A card as the shop sees it (contract MerchantCard). Needs `icon` loaded and
 * the `active_customers_count` and `rewards_ready_count` counts from
 * Card::scopeWithMerchantCounts().
 *
 * @mixin Card
 */
class MerchantCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new CardSummaryResource($this->resource))->toArray($request),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'suspended_at' => $this->suspended_at?->toIso8601ZuluString(),
            'active_customers' => (int) $this->active_customers_count,
            'rewards_ready' => (int) $this->rewards_ready_count,
        ];
    }
}
