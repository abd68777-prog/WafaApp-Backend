<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shop in the dashboard's list (requirements §5.3). Needs `businessType`
 * and `governorate` loaded, and the `activePeriod` relation and the
 * `subscription_ends_at` attribute set for the whole page at once.
 *
 * Unlike the merchant app, the dashboard sees the full phone number: it is the
 * shop's business line, not a customer's.
 *
 * @mixin Merchant
 */
class AdminMerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SubscriptionPeriod|null $period */
        $period = $this->resource->relationLoaded('activePeriod') ? $this->resource->getRelation('activePeriod') : null;

        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'owner_name' => $this->owner_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_url' => $this->logoUrl(),
            'business_type' => ['id' => $this->businessType->id, 'name' => $this->businessType->name],
            'governorate' => ['id' => $this->governorate->id, 'name' => $this->governorate->name],
            // Null until a package is chosen; `registration_step` says why.
            'status' => $this->status?->value,
            'registration_step' => $this->registrationStep(),
            'package' => $period ? new PackageSummaryResource($period->package) : null,
            'subscription_ends_at' => $this->getAttribute('subscription_ends_at')?->toIso8601ZuluString(),
            'suspended_at' => $this->suspended_at?->toIso8601ZuluString(),
            'suspension_reason' => $this->suspension_reason,
            'registered_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
