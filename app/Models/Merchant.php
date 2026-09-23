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

/**
 * A shop. One account, shared by the owner and the cashier, signed in through
 * Clerk; the PIN guards the sensitive tabs.
 *
 * Registration fills this row in three steps, so `status` is null until a
 * package is chosen and `pin_hash` until the PIN is set. The current package
 * is not stored here — it comes from the latest subscription period.
 *
 * Status, Clerk link and PIN are not mass assignable: they change through
 * registration and admin flows only. `email` is not either: it is copied from
 * the Clerk session token, never taken from request input.
 */
#[Fillable([
    'business_name',
    'business_type_id',
    'governorate_id',
    'address',
    'owner_name',
    'phone',
    'logo_path',
])]
#[Hidden(['pin_hash'])]
class Merchant extends Authenticatable
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchantStatus::class,
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Where the merchant is in the three registration screens.
     *
     * @return 'business'|'package'|'pin'|'done'
     */
    public function registrationStep(): string
    {
        return match (true) {
            $this->status === null => 'package',
            $this->pin_hash === null => 'pin',
            default => 'done',
        };
    }

    public function hasCompletedRegistration(): bool
    {
        return $this->registrationStep() === 'done';
    }

    /**
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    /**
     * @return BelongsTo<Governorate, $this>
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * @return HasMany<SubscriptionPeriod, $this>
     */
    public function subscriptionPeriods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class);
    }

    /**
     * The period that decides the current status: the one ending last.
     *
     * @return HasMany<SubscriptionPeriod, $this>
     */
    public function currentPeriod(): HasMany
    {
        return $this->subscriptionPeriods()->orderByDesc('ends_at')->limit(1);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * @return HasMany<CardCycle, $this>
     */
    public function cardCycles(): HasMany
    {
        return $this->hasMany(CardCycle::class);
    }

    /**
     * @return HasMany<Stamp, $this>
     */
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return MorphMany<DeviceToken, $this>
     */
    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'owner');
    }
}
