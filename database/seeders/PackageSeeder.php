<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * The three packages and their price matrix.
 *
 * Names, prices and campaign limits are placeholders until Deep Code confirms
 * them; all of it is edited from the dashboard afterwards.
 */
class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'الأساسية',
                'cards_limit' => 1,
                'weekly_campaigns_limit' => 1,
                'prices' => [1 => '10.00', 3 => '27.00', 12 => '96.00'],
            ],
            [
                'name' => 'المتوسطة',
                'cards_limit' => 2,
                'weekly_campaigns_limit' => 2,
                'prices' => [1 => '17.00', 3 => '46.00', 12 => '163.00'],
            ],
            [
                'name' => 'الشاملة',
                'cards_limit' => 5,
                'weekly_campaigns_limit' => 3,
                'prices' => [1 => '35.00', 3 => '95.00', 12 => '336.00'],
            ],
        ];

        foreach ($packages as $index => $attributes) {
            $package = Package::query()->updateOrCreate(
                ['name' => $attributes['name']],
                [
                    'cards_limit' => $attributes['cards_limit'],
                    'weekly_campaigns_limit' => $attributes['weekly_campaigns_limit'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );

            foreach ($attributes['prices'] as $months => $price) {
                $package->prices()->updateOrCreate(
                    ['duration_months' => $months],
                    ['price_usd' => $price],
                );
            }
        }
    }
}
