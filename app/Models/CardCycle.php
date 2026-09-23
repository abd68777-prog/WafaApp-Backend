<?php

namespace App\Models;

use App\Enums\CardCycleStatus;
use Database\Factories\CardCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The customer's copy of a card, for one cycle: from the first stamp to the
 * reward being handed over.
 *
 * Redemption does not reset a counter — it closes this row and opens a new
 * empty one, so it stays clear how many times the customer finished the card.
 */
#[Fillable(['card_id', 'customer_id', 'merchant_id'])]
class CardCycle extends Model
{
    /** @use HasFactory<CardCycleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stamps_count' => 'integer',
            'status' => CardCycleStatus::class,
            'completed_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * A completed cycle is locked until the reward is handed over.
     */
    public function acceptsStamps(): bool
    {
        return $this->status === CardCycleStatus::Collecting;
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
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
     * @return HasMany<Stamp, $this>
     */
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }
}
