<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A card as published (contract CardSummary). Needs `icon` loaded; the apps
 * draw the icon from their own library by its key.
 *
 * @mixin Card
 */
class CardSummaryResource extends JsonResource
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
            'stamps_required' => $this->stamps_required,
            'reward_description' => $this->reward_description,
            'terms' => $this->terms,
            'icon' => ['id' => $this->icon->id, 'key' => $this->icon->key, 'name' => $this->icon->name],
            'status' => $this->status->value,
        ];
    }
}
