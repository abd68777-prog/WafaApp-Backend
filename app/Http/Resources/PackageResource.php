<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package with its price matrix (contract PackageWithPrices). Needs
 * `prices` loaded.
 *
 * The Syrian pound amount uses today's exchange rate and is for display only:
 * the binding amount is copied into the payment when the proof is uploaded.
 *
 * @mixin Package
 */
class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $exchangeRate = (float) Setting::read('exchange_rate_syp', 0);

        return [
            ...(new PackageSummaryResource($this->resource))->toArray($request),
            'prices' => $this->prices
                ->sortBy('duration_months')
                ->map(fn (PackagePrice $price): array => [
                    'duration_months' => $price->duration_months,
                    'price_usd' => (string) $price->price_usd,
                    'amount_syp' => number_format((float) $price->price_usd * $exchangeRate, 2, '.', ''),
                ])
                ->values()
                ->all(),
        ];
    }
}
