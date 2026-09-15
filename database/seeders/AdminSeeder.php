<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Creates the platform owner account from ADMIN_EMAIL / ADMIN_PASSWORD.
 */
class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('auth.platform_admin.email');
        $password = config('auth.platform_admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL or ADMIN_PASSWORD is not set; skipping the platform admin account.');

            return;
        }

        Admin::query()->firstOrCreate(['email' => $email], [
            'name' => config('auth.platform_admin.name'),
            'password' => $password,
        ]);
    }
}
