<?php

namespace App\Http\Resources;

use App\Models\BusinessType;
use App\Models\Icon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business type or an icon as the dashboard edits it: disabled ones
 * included, with their order. `key` is present for icons only.
 *
 * @mixin BusinessType|Icon
 */
class AdminLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->when($this->resource instanceof Icon, fn () => $this->key),
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
