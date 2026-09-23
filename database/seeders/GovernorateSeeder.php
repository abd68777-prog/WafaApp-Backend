<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

/**
 * The 14 Syrian governorates. The first release serves Damascus only, but the
 * list is complete so the directory filter works as the platform grows.
 */
class GovernorateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $governorates = [
            'دمشق',
            'ريف دمشق',
            'حلب',
            'حمص',
            'حماة',
            'اللاذقية',
            'طرطوس',
            'إدلب',
            'دير الزور',
            'الرقة',
            'الحسكة',
            'درعا',
            'السويداء',
            'القنيطرة',
        ];

        foreach ($governorates as $index => $name) {
            Governorate::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
