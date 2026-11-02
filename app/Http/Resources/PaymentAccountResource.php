<?php

namespace App\Http\Resources;

use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment method as the merchant's payment screen shows it (contract
 * MerchantLookups.payment.methods). What each field holds depends on the
 * method: Sham Cash has the account code and QR, Al-Haram the recipient's
 * name and number.
 *
 * @mixin PaymentAccount
 */
class PaymentAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->method->value,
            'recipient_name' => $this->recipient_name,
            'account' => $this->account,
            'qr_url' => $this->qrUrl(),
            'note' => $this->note,
        ];
    }
}
