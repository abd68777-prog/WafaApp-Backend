<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * A payment in the review queue (requirements §5.2): the merchant's own view
 * of it, plus who paid, whether the review is late, and whether the same
 * reference was already used — so one receipt is never accepted twice.
 *
 * @mixin Payment
 */
class AdminPaymentResource extends PaymentResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            // Null once the shop's account is deleted.
            'merchant' => $this->merchant === null ? null : [
                'id' => $this->merchant->id,
                'business_name' => $this->merchant->business_name,
                'email' => $this->merchant->email,
                'status' => $this->merchant->status?->value,
            ],
            'overdue' => $this->status === PaymentStatus::Pending
                && $this->created_at->addHours((int) Setting::read('payment_review_sla_hours', 24))->isPast(),
            'duplicate_reference' => $this->reference !== null
                && Payment::query()->where('reference', $this->reference)->whereKeyNot($this->id)->exists(),
            'reviewed_by' => $this->reviewedBy === null ? null : [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ],
            'subscription_period_id' => $this->subscription_period_id,
        ];
    }
}
