<?php

namespace App\Models;

use App\Enums\SubscriptionPeriodType;
use Database\Factories\SubscriptionPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One trial or paid period. A renewal is a new row starting at the previous
 * end date, so the merchant loses nothing by paying early and gains nothing by
 * paying during the grace days.
 */
#[Fillable(['package_id', 'type', 'duration_months', 'starts_at', 'ends_at', 'grace_ends_at', 'note'])]
class SubscriptionPeriod extends Model
{
    /** @use HasFactory<SubscriptionPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubscriptionPeriodType::class,
            'duration_months' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
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
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }
}
