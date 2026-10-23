<?php

namespace Database\Seeders;

use App\Models\Icon;
use Illuminate\Database\Seeder;

/**
 * Starting icon library for loyalty cards. Every key is a Phosphor Icons name
 * (@phosphor-icons/core 2.1.1), so the apps draw it straight from the library.
 */
class IconSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $icons = [
            // مشروبات
            'coffee' => 'فنجان قهوة',
            'coffee-bean' => 'حبة قهوة',
            'orange-slice' => 'عصير',
            // مطاعم ووجبات
            'hamburger' => 'برغر',
            'pizza' => 'بيتزا',
            'bowl-food' => 'طبق',
            'bowl-steam' => 'طبق ساخن',
            'fork-knife' => 'شوكة وسكين',
            'cooking-pot' => 'طنجرة',
            'chef-hat' => 'قبعة شيف',
            'fish' => 'سمك',
            'shrimp' => 'جمبري',
            'popcorn' => 'بوشار',
            // خضار وفواكه
            'carrot' => 'جزر',
            'avocado' => 'أفوكادو',
            'cherries' => 'كرز',
            'leaf' => 'ورقة',
            // حلويات ومخابز
            'cake' => 'كيك',
            'cookie' => 'بسكويت',
            'ice-cream' => 'بوظة',
            'bread' => 'خبز',
            'cheese' => 'جبنة',
            // جمال وعناية
            'scissors' => 'مقص',
            'hair-dryer' => 'سشوار',
            'sparkle' => 'لمعة',
            'flower-lotus' => 'زهرة لوتس',
            'flower' => 'وردة',
            'flower-tulip' => 'توليب',
            'hand-soap' => 'صابون',
            // رياضة وصحة
            'barbell' => 'أثقال',
            'person-simple-run' => 'جري',
            'pill' => 'دواء',
            'first-aid-kit' => 'إسعاف',
            'tooth' => 'سن',
            // تسوق وخدمات
            'shopping-bag' => 'كيس تسوق',
            'basket' => 'سلة',
            't-shirt' => 'قميص',
            'dress' => 'فستان',
            'sneaker' => 'حذاء',
            'eyeglasses' => 'نظارة',
            'baby' => 'أطفال',
            'washing-machine' => 'غسيل',
            'car' => 'سيارة',
            'wrench' => 'صيانة',
            'paw-print' => 'حيوانات أليفة',
            'book-open' => 'كتاب',
            'game-controller' => 'ألعاب',
            // عام
            'gift' => 'هدية',
            'star' => 'نجمة',
            'heart' => 'قلب',
            'crown' => 'تاج',
            'ticket' => 'تذكرة',
            'seal-percent' => 'خصم',
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
