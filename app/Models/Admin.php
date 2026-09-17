<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A platform admin, signed in through Clerk.
 *
 * `clerk_user_id` is not mass assignable: linking a Clerk user to admin access
 * is done deliberately (AdminSeeder), never from request input.
 */
#[Fillable(['name', 'email'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
