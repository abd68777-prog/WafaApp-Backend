<?php

namespace App\Models;

use App\Enums\RewardStatus;
use App\Enums\RewardType;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An earned reward: a completed card or a birthday gift (PRD 8 "RewardHistory").
 */
#[Fillable([
    'customer_id',
    'merchant_id',
    'loyalty_card_id',
    'customer_card_progress_id',
    'type',
    'description',
    'status',
    'period_key',
    'earned_at',
    'redeemed_at',
    'expires_at',
])]
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RewardType::class,
            'status' => RewardStatus::class,
            'earned_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<LoyaltyCard, $this>
     */
    public function loyaltyCard(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class);
    }

    /**
     * @return BelongsTo<CustomerCardProgress, $this>
     */
    public function cardProgress(): BelongsTo
    {
        return $this->belongsTo(CustomerCardProgress::class, 'customer_card_progress_id');
    }
}
