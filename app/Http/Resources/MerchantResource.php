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
        $period = $this->whenLoaded('subscriptionPeriods', fn () => $this->subscriptionPeriods->sortByDesc('ends_at')->first());

        return [
            'id' => $this->id,
            'email' => $this->email,
            'business_name' => $this->business_name,
            'business_type' => $this->whenLoaded('businessType', fn (): array => [
                'id' => $this->businessType->id,
                'name' => $this->businessType->name,
            ]),
            'governorate' => $this->whenLoaded('governorate', fn (): array => [
                'id' => $this->governorate->id,
                'name' => $this->governorate->name,
            ]),
            'address' => $this->address,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'logo_url' => $this->logo_path ? url('storage/'.$this->logo_path) : null,
            'status' => $this->status?->value,
            'registration_step' => $this->registrationStep(),
            'has_pin' => $this->pin_hash !== null,
            'subscription' => $period ? [
                'package' => [
                    'id' => $period->package_id,
                    'name' => $period->package?->name,
                ],
                'type' => $period->type->value,
                'starts_at' => $period->starts_at?->toIso8601String(),
                'ends_at' => $period->ends_at?->toIso8601String(),
                'grace_ends_at' => $period->grace_ends_at?->toIso8601String(),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
