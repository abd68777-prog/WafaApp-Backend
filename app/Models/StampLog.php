<?php

namespace App\Models;

use App\Enums\StampSource;
use Database\Factories\StampLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stamp operation. `client_uuid` makes offline syncs idempotent.
 */
#[Fillable(['customer_id', 'loyalty_card_id', 'merchant_id', 'quantity', 'source', 'client_uuid', 'device_id', 'stamped_at'])]
class StampLog extends Model
{
    /** @use HasFactory<StampLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'source' => StampSource::class,
            'stamped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CustomerCardProgress, $this>
     */
    public function cardProgress(): BelongsTo
    {
        return $this->belongsTo(CustomerCardProgress::class, 'customer_card_progress_id');
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
}
