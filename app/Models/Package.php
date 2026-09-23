<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a subscription buys: how many loyalty cards the merchant may run and
 * how many campaigns they may send per week.
 */
#[Fillable(['name', 'cards_limit', 'weekly_campaigns_limit', 'is_active', 'sort_order'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cards_limit' => 'integer',
            'weekly_campaigns_limit' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<PackagePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PackagePrice::class);
    }

    /**
     * @return HasMany<SubscriptionPeriod, $this>
     */
    public function subscriptionPeriods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class);
    }
}
