<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            GovernorateSeeder::class,
            BusinessTypeSeeder::class,
            IconSeeder::class,
            PackageSeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Demo data for development and the staging server; never production.
        if (app()->environment(['local', 'staging'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
