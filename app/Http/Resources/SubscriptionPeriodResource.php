<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One trial or paid period (contract SubscriptionPeriod). Needs `package`.
 *
 * @mixin SubscriptionPeriod
 */
class SubscriptionPeriodResource extends JsonResource
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
            'type' => $this->type->value,
            'package' => new PackageSummaryResource($this->package),
            'duration_months' => $this->duration_months,
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'grace_ends_at' => $this->grace_ends_at?->toIso8601ZuluString(),
        ];
    }
}
