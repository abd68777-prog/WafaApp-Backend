<?php

namespace App\Models;

use App\Enums\CampaignAudience;
use App\Enums\CampaignStatus;
use Database\Factories\MerchantCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loyalty_card_id', 'title', 'body', 'audience', 'audience_filter'])]
class MerchantCampaign extends Model
{
    /** @use HasFactory<MerchantCampaignFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => CampaignAudience::class,
            'audience_filter' => 'array',
            'recipients_count' => 'integer',
            'status' => CampaignStatus::class,
            'sent_at' => 'datetime',
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
     * @return BelongsTo<LoyaltyCard, $this>
     */
    public function loyaltyCard(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class);
    }
}
