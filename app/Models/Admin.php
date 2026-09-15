<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The platform owner account that signs in to the admin dashboard.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasApiTokens, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Merchant, $this>
     */
    public function approvedMerchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'approved_by_admin_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function reviewedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'reviewed_by_admin_id');
    }
}
