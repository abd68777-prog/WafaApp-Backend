<?php

namespace App\Models;

use App\Enums\StampSource;
use Database\Factories\CustomerCardProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's progress on one loyalty card (PRD 8 "CustomerCardProgress").
 *
 * Stamps accumulate on `current_stamps`. When it reaches the card's
 * `stamps_required` a `ready` reward is created, and the counter resets to
 * zero only when the merchant confirms the reward was collected (PRD 3.3).
 * The counters are not mass assignable; change them with increments.
 */
#[Fillable(['customer_id', 'loyalty_card_id', 'merchant_id', 'enrolled_via'])]
class CustomerCardProgress extends Model
{
    /** @use HasFactory<CustomerCardProgressFactory> */
    use HasFactory;

    /**
     * "Progress" is uncountable, so the conventional table name is not guessed.
     */
    protected $table = 'customer_card_progress';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_stamps' => 'integer',
            'total_stamps' => 'integer',
            'rewards_earned' => 'integer',
            'enrolled_via' => StampSource::class,
            'last_stamped_at' => 'datetime',
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
     * @return BelongsTo<LoyaltyCard, $this>
     */
    public function loyaltyCard(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasMany<StampLog, $this>
     */
    public function stampLogs(): HasMany
    {
        return $this->hasMany(StampLog::class);
    }

    /**
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }
}
