<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

/**
 * Starting list of business types; the final one comes from Deep Code and is
 * managed from the dashboard afterwards.
 */
class BusinessTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'كافيه',
            'مطعم',
            'حلويات',
            'مخبز',
            'عصائر',
            'سوبرماركت',
            'صالون',
            'أخرى',
        ];

        foreach ($types as $index => $name) {
            BusinessType::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
