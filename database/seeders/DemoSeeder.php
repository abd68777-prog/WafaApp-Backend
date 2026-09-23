<?php

namespace Database\Seeders;

use App\Enums\CardCycleStatus;
use App\Enums\StampMethod;
use App\Models\BusinessType;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Icon;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Stamp;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Seeder;

/**
 * Local demo data covering the states the apps have to render: a card being
 * collected, one with the reward ready, a finished cycle, and a phone-only
 * customer whose stamps are waiting for them to sign up.
 *
 * The demo merchant is linked to the Clerk user id `user_demo_merchant`.
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $package = Package::query()->where('cards_limit', '>=', 2)->orderBy('cards_limit')->firstOrFail();
        $cafe = BusinessType::query()->where('name', 'كافيه')->firstOrFail();
        $damascus = Governorate::query()->where('name', 'دمشق')->firstOrFail();

        $merchant = Merchant::factory()->create([
            'clerk_user_id' => 'user_demo_merchant',
            'email' => 'demo-merchant@wafa.test',
            'business_name' => 'كافيه الياسمين',
            'business_type_id' => $cafe->id,
            'governorate_id' => $damascus->id,
            'address' => 'شارع الحمرا',
            'owner_name' => 'أحمد',
            'phone' => '+963900000001',
        ]);

        SubscriptionPeriod::factory()->trial((int) Setting::read('trial_days', 14))->create([
            'merchant_id' => $merchant->id,
            'package_id' => $package->id,
        ]);

        $coffee = Card::factory()->create([
            'merchant_id' => $merchant->id,
            'icon_id' => Icon::query()->where('key', 'coffee-cup')->value('id'),
            'name' => 'بطاقة القهوة',
            'stamps_required' => 5,
            'reward_description' => 'قهوة مجانية من اختيارك',
            'terms' => 'لا تشمل المشروبات المثلجة',
        ]);

        Card::factory()->create([
            'merchant_id' => $merchant->id,
            'icon_id' => Icon::query()->where('key', 'cake-slice')->value('id'),
            'name' => 'بطاقة الحلويات',
            'stamps_required' => 8,
            'reward_description' => 'قطعة حلوى مجانية',
        ]);

        // Collecting: three of five.
        $sara = Customer::factory()->create(['phone' => '+963900000010', 'name' => 'سارة']);
        $this->cycleWithStamps($coffee, $sara, 3);

        // Reward ready: the card is full and locked until the merchant hands it over.
        $omar = Customer::factory()->create(['phone' => '+963900000011', 'name' => 'عمر']);
        $ready = $this->cycleWithStamps($coffee, $omar, 5);
        $ready->forceFill(['status' => CardCycleStatus::RewardReady, 'completed_at' => now()])->save();

        // A finished cycle plus the new empty one that opened on redemption.
        $lina = Customer::factory()->create(['phone' => '+963900000012', 'name' => 'لينا']);
        $redeemed = $this->cycleWithStamps($coffee, $lina, 5);
        $redeemed->forceFill([
            'status' => CardCycleStatus::Redeemed,
            'completed_at' => now()->subWeek(),
            'redeemed_at' => now()->subDays(6),
        ])->save();
        $this->cycleWithStamps($coffee, $lina, 1);

        // Phone-only customer: their stamps wait until they sign up.
        $pending = Customer::factory()->pending()->create(['phone' => '+963900000099']);
        $this->cycleWithStamps($coffee, $pending, 2, StampMethod::Phone);
    }

    private function cycleWithStamps(Card $card, Customer $customer, int $stamps, StampMethod $method = StampMethod::Qr): CardCycle
    {
        $cycle = CardCycle::factory()->create([
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
            'stamps_count' => $stamps,
        ]);

        Stamp::factory()->count($stamps)->create([
            'card_cycle_id' => $cycle->id,
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
            'method' => $method,
        ]);

        return $cycle;
    }
}
