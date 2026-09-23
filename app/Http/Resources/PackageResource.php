<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package with its price matrix, for the package-selection and payment
 * screens.
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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cards_limit' => $this->cards_limit,
            'weekly_campaigns_limit' => $this->weekly_campaigns_limit,
            'prices' => $this->whenLoaded('prices', fn (): array => $this->prices
                ->sortBy('duration_months')
                ->map(fn (PackagePrice $price): array => [
                    'duration_months' => $price->duration_months,
                    'price_usd' => $price->price_usd,
                ])
                ->values()
                ->all()),
        ];
    }
}
