<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * The three packages from PRD 6. Prices are the PRD's placeholder values until
 * the team confirms them; yearly prices are left empty for the same reason.
 */
class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            ['code' => 'basic', 'name' => 'الأساسية', 'max_cards' => 1, 'price_monthly_usd' => '10.00', 'sort_order' => 1],
            ['code' => 'standard', 'name' => 'المتوسطة', 'max_cards' => 2, 'price_monthly_usd' => '17.00', 'sort_order' => 2],
            ['code' => 'premium', 'name' => 'الشاملة', 'max_cards' => 5, 'price_monthly_usd' => '35.00', 'sort_order' => 3],
        ];

        foreach ($packages as $package) {
            Package::query()->firstOrCreate(['code' => $package['code']], $package);
        }
    }
}
