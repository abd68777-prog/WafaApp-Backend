<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Database\Seeder;

/**
 * Links the platform owner's Clerk user to a super admin account, from
 * ADMIN_CLERK_USER_ID and ADMIN_EMAIL.
 *
 * Note that a super admin cannot approve payments; reviewing them needs a
 * second account with the payments reviewer role.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clerkUserId = config('auth.platform_admin.clerk_user_id');
        $email = config('auth.platform_admin.email');

        if (blank($clerkUserId) || blank($email)) {
            $this->command?->warn('ADMIN_CLERK_USER_ID or ADMIN_EMAIL is not set; skipping the platform admin account.');

            return;
        }

        $admin = AdminUser::query()->firstOrNew(['email' => $email]);

        $admin->fill([
            'name' => config('auth.platform_admin.name'),
            'role' => AdminRole::SuperAdmin,
            'is_active' => true,
        ]);

        $admin->forceFill(['clerk_user_id' => $clerkUserId])->save();
    }
}
