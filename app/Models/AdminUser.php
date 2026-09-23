<?php

namespace App\Models;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Database\Factories\AdminUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * A dashboard account. Signing in happens through Clerk; the role here decides
 * what the account may do.
 *
 * `clerk_user_id` is not mass assignable: linking a Clerk user to a dashboard
 * role is a deliberate act, never request input.
 */
#[Fillable(['name', 'email', 'role', 'is_active'])]
class AdminUser extends Authenticatable
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AdminRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function hasRole(AdminRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasPermission(AdminPermission $permission): bool
    {
        return in_array($permission, $this->role->permissions(), true);
    }

    /**
     * An account added from the dashboard stays unlinked until its owner signs
     * in to Clerk for the first time with the same email.
     */
    public function isLinked(): bool
    {
        return $this->clerk_user_id !== null;
    }

    /**
     * Stored lowercase, so the first-sign-in match against the Clerk email is
     * exact.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
