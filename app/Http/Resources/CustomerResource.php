<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer's own profile, for the customer app only.
 *
 * `qr_secret` is the seed the app uses to generate the rotating QR code
 * offline; it never appears in any merchant-facing payload.
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
        $acceptedVersion = $this->acceptedPolicyVersion();
        $currentVersion = (string) Setting::read('privacy_policy_version', '1.2');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate?->toDateString(),
            'qr_secret' => $this->qr_secret,
            'qr_period_seconds' => (int) Setting::read('qr_period_seconds', 60),
            'campaigns_muted' => $this->campaigns_muted,
            'registered_at' => $this->registered_at?->toIso8601String(),
            // When the policy changes materially the app tells the customer
            // and records their agreement through `POST /customer/policy/accept`.
            'policy' => [
                'accepted_version' => $acceptedVersion,
                'current_version' => $currentVersion,
                'update_required' => $acceptedVersion !== $currentVersion,
            ],
        ];
    }
}
