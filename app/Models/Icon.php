<?php

namespace App\Models;

use Database\Factories\IconFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The icon library a merchant picks from when creating a card. Cards carry no
 * uploaded artwork, so they all look consistent and nothing needs moderation.
 */
#[Fillable(['key', 'name', 'sort_order', 'is_active'])]
class Icon extends Model
{
    /** @use HasFactory<IconFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
