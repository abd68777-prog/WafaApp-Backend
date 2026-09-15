<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A customer identified by phone number, signing in with an OTP.
 *
 * `qr_token` is the single permanent QR identity shared by every merchant
 * (PRD 7). It is null while the customer is `pending`.
 */
#[Fillable(['phone', 'name', 'birthdate', 'avatar_path', 'locale'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'birthdate' => 'date',
            'phone_verified_at' => 'datetime',
            'registered_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CustomerCardProgress, $this>
     */
    public function cardProgress(): HasMany
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
     * @return MorphMany<DeviceToken, $this>
     */
    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(DeviceToken::class, 'owner');
    }
}
