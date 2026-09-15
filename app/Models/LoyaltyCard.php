<?php

namespace App\Models;

use Database\Factories\LoyaltyCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One loyalty offer of a merchant, e.g. "hot drinks" (PRD 4.4).
 */
#[Fillable(['name', 'description', 'stamps_required', 'reward_description', 'image_path', 'is_active', 'sort_order'])]
class LoyaltyCard extends Model
{
    /** @use HasFactory<LoyaltyCardFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stamps_required' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
     * @return HasMany<CustomerCardProgress, $this>
     */
    public function customerProgress(): HasMany
    {
        return $this->hasMany(CustomerCardProgress::class);
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

    /**
     * @return HasMany<MerchantCampaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(MerchantCampaign::class);
    }
}
