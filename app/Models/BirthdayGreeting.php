<?php

namespace App\Models;

use Database\Factories\BirthdayGreetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Birthday greetings are sent by hand, never automatically. The unique triple
 * (merchant, customer, day) means tapping send twice still sends one.
 */
#[Fillable(['merchant_id', 'customer_id', 'greeted_on'])]
class BirthdayGreeting extends Model
{
    /** @use HasFactory<BirthdayGreetingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'greeted_on' => 'date',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
