<?php

namespace App\Models;

use Database\Factories\MerchantMuteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer switched off one shop's offers. Protects the other merchants from
 * the annoying one, instead of the customer muting every shop at once.
 */
#[Fillable(['customer_id', 'merchant_id'])]
class MerchantMute extends Model
{
    /** @use HasFactory<MerchantMuteFactory> */
    use HasFactory;

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
}
