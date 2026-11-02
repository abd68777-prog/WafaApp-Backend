<?php

namespace App\Http\Resources;

use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method in the dashboard's billing settings: what the merchant
 * sees, plus whether it is offered and in which position.
 *
 * @mixin PaymentAccount
 */
class AdminPaymentAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new PaymentAccountResource($this->resource))->toArray($request),
            'name' => $this->method->label(),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
