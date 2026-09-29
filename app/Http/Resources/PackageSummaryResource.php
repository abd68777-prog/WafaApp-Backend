<?php

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package and its limits (contract PackageSummary).
 *
 * @mixin Package
 */
class PackageSummaryResource extends JsonResource
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
        ];
    }
}
