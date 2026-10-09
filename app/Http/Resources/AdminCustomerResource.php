<?php

namespace App\Http\Resources;

use App\Models\CardCycle;
use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer as the dashboard sees them (requirements §5.4). The number stays
 * masked here; the full one comes only from `reveal-phone`, which is audited.
 * `cycles` appear when loaded, newest activity first.
 *
 * @mixin Customer
 */
class AdminCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_masked' => $this->phone === null ? null : PhoneNumber::mask($this->phone),
            'birthdate' => $this->birthdate?->toDateString(),
            'registered' => ! $this->isPending(),
            'registered_at' => $this->registered_at?->toIso8601ZuluString(),
            'last_activity_at' => $this->last_activity_at?->toIso8601ZuluString(),
            'campaigns_muted' => $this->campaigns_muted,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'cycles' => $this->whenLoaded('cardCycles', fn () => $this->cardCycles->map(fn (CardCycle $cycle): array => [
                'id' => $cycle->id,
                'merchant' => ['id' => $cycle->merchant->id, 'business_name' => $cycle->merchant->business_name],
                'card' => ['id' => $cycle->card->id, 'name' => $cycle->card->name, 'status' => $cycle->card->status->value],
                'stamps_count' => $cycle->stamps_count,
                'stamps_required' => $cycle->card->stamps_required,
                'status' => $cycle->status->value,
                'started_at' => $cycle->created_at->toIso8601ZuluString(),
                'completed_at' => $cycle->completed_at?->toIso8601ZuluString(),
                'redeemed_at' => $cycle->redeemed_at?->toIso8601ZuluString(),
            ])->all()),
        ];
    }
}
