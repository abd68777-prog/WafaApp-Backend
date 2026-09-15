<?php

namespace Database\Seeders;

use App\Enums\StampSource;
use App\Models\Customer;
use App\Models\CustomerCardProgress;
use App\Models\LoyaltyCard;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Reward;
use App\Models\StampLog;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

/**
 * Local demo data covering the PRD 7.1 scenarios: returning customers, a
 * completed card with a reward ready, and a pending phone-only customer.
 *
 * Demo merchant login: merchant@example.com / password.
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $premium = Package::query()->where('code', 'premium')->firstOrFail();

        $merchant = Merchant::factory()->for($premium)->create([
            'business_name' => 'كافيه الياسمين',
            'phone' => '+963900000001',
            'email' => 'merchant@example.com',
        ]);

        Subscription::factory()->for($merchant)->for($premium)->create([
            'price_usd' => $premium->price_monthly_usd,
        ]);

        $cards = LoyaltyCard::factory()->for($merchant)->count(3)->sequence(
            ['name' => 'مشروبات ساخنة', 'stamps_required' => 5, 'reward_description' => 'مشروب ساخن مجاني', 'sort_order' => 1],
            ['name' => 'مشروبات باردة', 'stamps_required' => 6, 'reward_description' => 'مشروب بارد مجاني', 'sort_order' => 2],
            ['name' => 'حلويات', 'stamps_required' => 8, 'reward_description' => 'قطعة حلوى مجانية', 'sort_order' => 3],
        )->create();

        $hotDrinks = $cards->first();

        Customer::factory()->count(4)->create()->each(function (Customer $customer) use ($hotDrinks): void {
            $this->stamp($customer, $hotDrinks, 3, StampSource::Qr);
        });

        $loyalCustomer = Customer::factory()->create(['phone' => '+963900000010', 'name' => 'سارة']);
        $completedProgress = $this->stamp($loyalCustomer, $hotDrinks, $hotDrinks->stamps_required, StampSource::Qr);

        Reward::factory()->for($completedProgress, 'cardProgress')->create([
            'description' => $hotDrinks->reward_description,
        ]);

        $pendingCustomer = Customer::factory()->pending()->create(['phone' => '+963900000099']);
        $this->stamp($pendingCustomer, $hotDrinks, 1, StampSource::Phone);
    }

    private function stamp(Customer $customer, LoyaltyCard $card, int $stamps, StampSource $source): CustomerCardProgress
    {
        $progress = CustomerCardProgress::factory()->for($customer)->for($card)->create([
            'current_stamps' => $stamps,
            'total_stamps' => $stamps,
            'enrolled_via' => $source,
            'last_stamped_at' => now(),
        ]);

        StampLog::factory()->count($stamps)->for($progress, 'cardProgress')->create([
            'source' => $source,
        ]);

        return $progress;
    }
}
