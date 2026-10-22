<?php

namespace Database\Seeders;

use App\Models\Icon;
use Illuminate\Database\Seeder;

/**
 * Starting icon library for loyalty cards. The apps map `key` to a drawing, so
 * no artwork is uploaded and every card looks consistent.
 */
class IconSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $icons = [
            'coffee' => 'فنجان قهوة',
            'cake' => 'كيك',
            'ice-cream' => 'بوظة',
            'pizza' => 'بيتزا',
            'bread' => 'خبز',
            'scissors' => 'حلاقة',
            'gift' => 'هدية',
        ];

        $order = 1;

        foreach ($icons as $key => $name) {
            Icon::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'sort_order' => $order++, 'is_active' => true],
            );
        }
    }
}
