<?php

namespace App\Http\Resources;

use App\Models\CardCycle;
use App\Models\Customer;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer as the shop sees them (contract MerchantCustomer): the name,
 * never the full number, and where they stand on the shop's cards.
 *
 * @mixin Customer
 */
class MerchantCustomerResource extends JsonResource
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
            'registered' => $this->registered_at !== null,
            'last_stamp_at' => $this->shop_last_stamp_at === null
                ? null
                : CarbonImmutable::parse($this->shop_last_stamp_at, 'UTC')->toIso8601ZuluString(),
            'cards' => $this->openCycles->map(fn (CardCycle $cycle): array => [
                'card_id' => $cycle->card_id,
                'card_name' => $cycle->card->name,
                'stamps_count' => $cycle->stamps_count,
                'stamps_required' => $cycle->card->stamps_required,
                'status' => $cycle->status->value,
            ])->values()->all(),
        ];
    }
}
