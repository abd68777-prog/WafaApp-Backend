<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Links the platform owner's Clerk user to admin access, from
 * ADMIN_CLERK_USER_ID and ADMIN_EMAIL. Safe to run again: it updates the
 * existing admin row matched by email.
 */
class AdminSeeder extends Seeder
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

        $admin = Admin::query()->firstOrNew(['email' => $email]);

        $admin->fill(['name' => config('auth.platform_admin.name')]);
        $admin->forceFill(['clerk_user_id' => $clerkUserId])->save();
    }
}
