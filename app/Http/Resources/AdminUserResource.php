<?php

namespace App\Http\Resources;

use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AdminUser */
class AdminUserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            // False until the owner of the email signs in to Clerk once.
            'linked' => $this->isLinked(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
