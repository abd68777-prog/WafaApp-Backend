<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shop as the customer app shows it on a card (contract MerchantSummary).
 * Needs `businessType` and `governorate` loaded.
 *
 * @mixin Merchant
 */
class MerchantSummaryResource extends JsonResource
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
            'business_name' => $this->business_name,
            'logo_url' => $this->logoUrl(),
            'business_type' => ['id' => $this->businessType->id, 'name' => $this->businessType->name],
            'governorate' => ['id' => $this->governorate->id, 'name' => $this->governorate->name],
        ];
    }
}
