<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit trail the requirements ask for: who did what, to whom, with
 * the values before and after, and when (§5.1).
 *
 * The subject is stored by its morph alias (`admin`, `merchant`, `customer`),
 * so renaming a model never orphans the trail.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        ?AdminUser $admin,
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'admin_user_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress,
        ]);
    }
}
