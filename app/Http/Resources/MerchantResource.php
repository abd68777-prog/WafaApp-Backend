<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
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
            'business_name' => $this->business_name,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'address' => $this->address,
            'status' => $this->status->value,
            'package' => $this->whenLoaded('package', fn (): array => [
                'code' => $this->package->code,
                'name' => $this->package->name,
                'max_cards' => $this->package->max_cards,
            ]),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'subscription_ends_at' => $this->subscription_ends_at?->toIso8601String(),
            'birthday_gift_enabled' => $this->birthday_gift_enabled,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
