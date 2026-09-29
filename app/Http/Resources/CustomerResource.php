<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer's own account, for the customer app only (contract Customer).
 *
 * The app compares `consented_policy_version` with the version in
 * `GET /customer/config` to decide whether to show the consent screen, and
 * `profile_complete` to decide whether to ask for the name and birthdate.
 * The QR secret is not here: it has its own endpoint.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
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
            'phone' => $this->phone,
            'name' => $this->name,
            'birthdate' => $this->birthdate?->toDateString(),
            'campaigns_muted' => $this->campaigns_muted,
            'profile_complete' => $this->hasCompleteProfile(),
            'consented_policy_version' => $this->acceptedPolicyVersion(),
            'registered_at' => $this->registered_at?->toIso8601ZuluString(),
        ];
    }
}
