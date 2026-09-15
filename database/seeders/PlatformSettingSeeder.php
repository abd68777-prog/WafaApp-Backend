<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

/**
 * Default platform settings. Existing values are never overwritten, so values
 * an admin changed survive a re-seed. The trial length is a placeholder until
 * the team settles PRD 11.
 */
class PlatformSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'trial_days' => 14,
            'subscription_reminder_days' => 3,
        ];

        foreach ($defaults as $key => $value) {
            PlatformSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
