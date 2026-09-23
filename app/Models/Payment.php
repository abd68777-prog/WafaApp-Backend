<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual transfer with its proof.
 *
 * The package, duration, USD price, exchange rate and SYP amount are copied
 * into the row when the merchant uploads the proof: the price table says what
 * the price is now, this row says what this merchant paid then.
 *
 * The review decision is not mass assignable — only the reviewer flow sets it.
 */
#[Fillable([
    'package_id',
    'duration_months',
    'price_usd',
    'exchange_rate',
    'amount_syp',
    'method',
    'reference',
    'proof_path',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'price_usd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_syp' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'rejection_reason' => PaymentRejectionReason::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_id');
    }

    /**
     * @return BelongsTo<SubscriptionPeriod, $this>
     */
    public function subscriptionPeriod(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPeriod::class);
    }
}
