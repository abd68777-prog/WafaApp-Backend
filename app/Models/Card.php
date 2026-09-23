<?php

namespace App\Models;

use App\Enums\CardStatus;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A loyalty card as the merchant published it, e.g. "every tenth coffee free".
 *
 * It is never edited after publishing, only suspended: raising the required
 * stamps from 10 to 15 on a customer who already has 9 would be a cheat, and
 * removing the route is safer than hiding the button.
 */
#[Fillable(['icon_id', 'name', 'stamps_required', 'reward_description', 'terms'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stamps_required' => 'integer',
            'status' => CardStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * A suspended card takes no new customers but existing ones may finish it.
     */
    public function acceptsNewCustomers(): bool
    {
        return $this->status === CardStatus::Active;
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Icon, $this>
     */
    public function icon(): BelongsTo
    {
        return $this->belongsTo(Icon::class);
    }

    /**
     * @return HasMany<CardCycle, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(CardCycle::class);
    }
}
