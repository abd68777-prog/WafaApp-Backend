<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A customer, identified by phone number.
 *
 * A merchant may add someone by phone alone: that is a row here with
 * `registered_at` null — a "pending" customer. Signing up with the same number
 * fills this same row, so the stamps collected meanwhile are already theirs.
 *
 * A deleted account is soft deleted and emptied of personal data (see
 * CustomerAccountDeleter): its stamps still count for merchants, anonymously.
 */
#[Fillable(['phone', 'name', 'birthdate', 'campaigns_muted'])]
#[Hidden(['qr_secret'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'campaigns_muted' => 'boolean',
            'registered_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * A pending customer exists only as a phone number a merchant typed.
     */
    public function isPending(): bool
    {
        return $this->registered_at === null;
    }

    /**
     * @return HasMany<PolicyConsent, $this>
     */
    public function policyConsents(): HasMany
    {
        return $this->hasMany(PolicyConsent::class);
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
     * Merchants whose campaigns this customer switched off.
     *
     * @return HasMany<MerchantMute, $this>
     */
    public function merchantMutes(): HasMany
    {
        return $this->hasMany(MerchantMute::class);
    }

    /**
     * @return HasMany<BirthdayGreeting, $this>
     */
    public function birthdayGreetings(): HasMany
    {
        return $this->hasMany(BirthdayGreeting::class);
    }

    /**
     * @return MorphMany<DeviceToken, $this>
     */
    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'owner');
    }

    /**
     * The policy version this customer last agreed to, or null if none.
     */
    public function acceptedPolicyVersion(): ?string
    {
        return $this->policyConsents()->latest('consented_at')->latest('id')->value('policy_version');
    }
}
