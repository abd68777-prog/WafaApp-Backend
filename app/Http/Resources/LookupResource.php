<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared shape for the managed lists the apps show in dropdowns
 * (governorates, business types, icons).
 */
class LookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'key' => $this->key ?? null,
            'name' => $this->name,
        ], fn (mixed $value): bool => $value !== null);
    }
}
