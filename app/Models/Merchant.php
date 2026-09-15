<?php

namespace App\Models;

use App\Enums\MerchantStatus;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A merchant account — exactly one business (PRD v1.1).
 *
 * Package, status, approval and subscription dates are deliberately not
 * mass assignable: only admin flows may change them (PRD 4.2).
 */
#[Fillable([
    'owner_name',
    'business_name',
    'phone',
    'email',
    'password',
    'logo_path',
    'address',
    'city',
    'birthday_gift_enabled',
    'birthday_gift_description',
])]
#[Hidden(['password'])]
class Merchant extends Authenticatable
{
    /** @use HasFactory<MerchantFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => MerchantStatus::class,
            'approved_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'birthday_gift_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<LoyaltyCard, $this>
     */
    public function loyaltyCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class);
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

    /**
     * @return MorphMany<DeviceToken, $this>
     */
    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'owner');
    }
}
