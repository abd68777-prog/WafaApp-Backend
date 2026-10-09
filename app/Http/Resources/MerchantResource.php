<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The shop's own profile, for the merchant app (contract MerchantProfile).
 * Needs `businessType` and `governorate` loaded.
 *
 * @mixin Merchant
 */
class MerchantResource extends JsonResource
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
            'email' => $this->email,
            'business_name' => $this->business_name,
            'business_type' => ['id' => $this->businessType->id, 'name' => $this->businessType->name],
            'governorate' => ['id' => $this->governorate->id, 'name' => $this->governorate->name],
            'address' => $this->address,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'contact_phone' => $this->contact_phone,
            'logo_url' => $this->logoUrl(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
