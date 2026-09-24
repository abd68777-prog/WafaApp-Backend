<?php

namespace Database\Seeders;

use App\Enums\CardCycleStatus;
use App\Enums\MerchantStatus;
use App\Enums\StampMethod;
use App\Enums\SubscriptionPeriodType;
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
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for the store review account (REVIEW_PHONE): two shops, a card
 * being collected and a card with its reward ready, so reviewers see every
 * screen of the customer app populated.
 *
 * Runs in production, where Faker is not installed, so every row is created
 * explicitly rather than through factories. Safe to run again: it only adds
 * what is missing.
 *
 * The demo shops use Clerk ids no Clerk user has, so nobody can sign in to
 * them as a merchant.
 */
class ReviewAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $phone = PhoneNumber::normalize((string) config('otp.review_phone'));

        if ($phone === null) {
            $this->command?->warn('REVIEW_PHONE is not set to a valid Syrian mobile number; skipping the review account.');

            return;
        }

        $reviewer = $this->reviewer($phone);

        $cafe = $this->shop('review_demo_cafe', 'كافيه وفاء التجريبي', 'كافيه', '+963900000201');
        $coffee = $this->card($cafe, 'coffee-cup', 'بطاقة القهوة', 'قهوة مجانية من اختيارك');
        $this->cycle($coffee, $reviewer, 3, CardCycleStatus::Collecting);

        $sweets = $this->shop('review_demo_sweets', 'حلويات وفاء التجريبية', 'حلويات', '+963900000202');
        $cake = $this->card($sweets, 'cake-slice', 'بطاقة الحلويات', 'قطعة كيك مجانية');
        $this->cycle($cake, $reviewer, 5, CardCycleStatus::RewardReady);
    }

    private function reviewer(string $phone): Customer
    {
        $customer = Customer::query()->firstOrNew(['phone' => $phone]);

        $customer->fill([
            'name' => $customer->name ?? 'Store Review',
            'birthdate' => $customer->birthdate ?? '1990-01-01',
        ]);

        $customer->forceFill([
            'qr_secret' => $customer->qr_secret ?? Str::random(40),
            'registered_at' => $customer->registered_at ?? now(),
            'last_activity_at' => now(),
        ])->save();

        $customer->policyConsents()->firstOrCreate(
            ['policy_version' => (string) Setting::read('privacy_policy_version', '1.2')],
            ['consented_at' => now()],
        );

        return $customer;
    }

    private function shop(string $clerkUserId, string $name, string $businessType, string $phone): Merchant
    {
        $existing = Merchant::query()->where('clerk_user_id', $clerkUserId)->first();

        if ($existing) {
            return $existing;
        }

        $merchant = new Merchant([
            'business_name' => $name,
            'business_type_id' => BusinessType::query()->where('name', $businessType)->value('id')
                ?? BusinessType::query()->orderBy('sort_order')->value('id'),
            'governorate_id' => Governorate::query()->orderBy('sort_order')->value('id'),
            'owner_name' => 'Wafa Review',
            'phone' => $phone,
        ]);

        $merchant->forceFill([
            'clerk_user_id' => $clerkUserId,
            'status' => MerchantStatus::Active,
            // Nobody signs in to a demo shop; the PIN only completes registration.
            'pin_hash' => Hash::make(Str::random(32)),
        ])->save();

        // A long paid period keeps the demo shops active and in the directory.
        $merchant->subscriptionPeriods()->create([
            'package_id' => Package::query()->where('is_active', true)->orderBy('sort_order')->value('id'),
            'type' => SubscriptionPeriodType::Paid,
            'duration_months' => 12,
            'starts_at' => now(),
            'ends_at' => now()->addYears(10),
            'note' => 'Store review demo shop',
        ]);

        return $merchant;
    }

    private function card(Merchant $merchant, string $iconKey, string $name, string $reward): Card
    {
        $existing = Card::query()->where('merchant_id', $merchant->id)->where('name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $card = new Card([
            'icon_id' => Icon::query()->where('key', $iconKey)->value('id'),
            'name' => $name,
            'stamps_required' => 5,
            'reward_description' => $reward,
        ]);

        $card->forceFill(['merchant_id' => $merchant->id])->save();

        return $card;
    }

    private function cycle(Card $card, Customer $customer, int $stamps, CardCycleStatus $status): void
    {
        $exists = CardCycle::query()
            ->where('card_id', $card->id)
            ->where('customer_id', $customer->id)
            ->exists();

        if ($exists) {
            return;
        }

        $cycle = CardCycle::query()->create([
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
        ]);

        $cycle->forceFill([
            'stamps_count' => $stamps,
            'status' => $status,
            'completed_at' => $status === CardCycleStatus::RewardReady ? now() : null,
        ])->save();

        foreach (range(1, $stamps) as $day) {
            Stamp::query()->create([
                'card_cycle_id' => $cycle->id,
                'card_id' => $card->id,
                'customer_id' => $customer->id,
                'merchant_id' => $card->merchant_id,
                'method' => StampMethod::Qr,
                'client_uuid' => (string) Str::uuid(),
                'stamped_at' => now()->subDays($stamps - $day + 1),
            ]);
        }
    }
}
