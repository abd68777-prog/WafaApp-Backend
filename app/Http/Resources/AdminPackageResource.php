<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package as the dashboard edits it: inactive ones included, prices in
 * USD only (the SYP amount follows the exchange rate setting).
 *
 * @mixin Package
 */
class AdminPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cards_limit' => $this->cards_limit,
            'weekly_campaigns_limit' => $this->weekly_campaigns_limit,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'prices' => $this->prices
                ->sortBy('duration_months')
                ->map(fn (PackagePrice $price): array => [
                    'duration_months' => $price->duration_months,
                    'price_usd' => (string) $price->price_usd,
                ])
                ->values()
                ->all(),
        ];
    }
}
