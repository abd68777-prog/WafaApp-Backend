<?php

namespace Database\Seeders;

use App\Enums\CardColor;
use App\Enums\CardCycleStatus;
use App\Enums\MerchantStatus;
use App\Enums\StampMethod;
use App\Enums\SubscriptionPeriodType;
use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Governorate;
use App\Models\Icon;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Stamp;
use App\Notifications\CampaignReceived;
use App\Notifications\CardCompleted;
use App\Notifications\RewardRedeemed;
use App\Notifications\StampAdded;
use App\Services\Customer\CustomerQrCode;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for the store review account (REVIEW_PHONE), so reviewers see
 * every screen of the customer app populated: four shops with addresses and
 * contact numbers, a card being collected, a reward ready, a reward already
 * collected with its next card started, a shop not joined yet, a campaign,
 * and an inbox with read and unread notifications.
 *
 * Runs in production, where Faker is not installed, so every row is created
 * explicitly rather than through factories. Safe to run again: it only adds
 * what is missing, which is how `review:prepare` refills the account after
 * a reviewer deletes it and signs in again.
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

        $cafe = $this->shop('review_demo_cafe', 'كافيه وفاء التجريبي', 'كافيه', '+963900000201', 'شارع الحمرا، دمشق', '+963112223301');
        $coffee = $this->card($cafe, 'coffee', CardColor::Coffee, 'بطاقة القهوة', 'قهوة مجانية من اختيارك', 5);
        $collecting = $this->cycle($coffee, $reviewer, 3, CardCycleStatus::Collecting);

        $sweets = $this->shop('review_demo_sweets', 'حلويات وفاء التجريبية', 'حلويات', '+963900000202', 'شارع بغداد، دمشق', '+963112223302');
        $cake = $this->card($sweets, 'cake', CardColor::Rose, 'بطاقة الحلويات', 'قطعة كيك مجانية', 5);
        $ready = $this->cycle($cake, $reviewer, 5, CardCycleStatus::RewardReady);

        $bakery = $this->shop('review_demo_bakery', 'فرن وفاء التجريبي', 'مخبز', '+963900000203', 'المزة، دمشق', '+963112223303');
        $bread = $this->card($bakery, 'bread', CardColor::Olive, 'بطاقة الخبز', 'ربطة خبز مجانية', 4);
        $redeemed = $this->cycle($bread, $reviewer, 4, CardCycleStatus::Redeemed);
        $this->cycle($bread, $reviewer, 1, CardCycleStatus::Collecting);

        // A shop the reviewer has not joined, to find in the directory.
        $restaurant = $this->shop('review_demo_restaurant', 'مطعم وفاء التجريبي', 'مطعم', '+963900000204', 'باب توما، دمشق', '+963112223304');
        $this->card($restaurant, 'hamburger', CardColor::Burgundy, 'بطاقة البرغر', 'وجبة برغر مجانية', 6);

        $campaign = $this->campaign($cafe, 'عرض نهاية الأسبوع', 'خصم 20% على كل المشروبات الباردة يومي الجمعة والسبت.');

        $this->inbox($reviewer, [
            new RewardRedeemed($redeemed, $bread),
            new CampaignReceived($campaign, $cafe->business_name),
        ], [
            new CardCompleted($ready, $cake),
            new StampAdded($collecting, $coffee),
        ]);
    }

    private function reviewer(string $phone): Customer
    {
        $customer = Customer::query()->firstOrNew(['phone' => $phone]);

        $customer->fill([
            'name' => $customer->name ?? 'Store Review',
            'birthdate' => $customer->birthdate ?? '1990-01-01',
        ]);

        $customer->forceFill([
            'registered_at' => $customer->registered_at ?? now(),
            'last_activity_at' => now(),
        ]);

        app(CustomerQrCode::class)->assignTo($customer);
        $customer->save();

        $customer->policyConsents()->firstOrCreate(
            ['policy_version' => (string) Setting::read('privacy_policy_version', '1.2')],
            ['consented_at' => now()],
        );

        return $customer;
    }

    private function shop(string $clerkUserId, string $name, string $businessType, string $phone, string $address, string $contactPhone): Merchant
    {
        $existing = Merchant::query()->where('clerk_user_id', $clerkUserId)->first();

        if ($existing) {
            // Shops seeded before addresses were part of the demo get them now.
            $existing->forceFill([
                'address' => $existing->address ?? $address,
                'contact_phone' => $existing->contact_phone ?? $contactPhone,
            ])->save();

            return $existing;
        }

        $merchant = new Merchant([
            'business_name' => $name,
            'business_type_id' => BusinessType::query()->where('name', $businessType)->value('id')
                ?? BusinessType::query()->orderBy('sort_order')->value('id'),
            'governorate_id' => Governorate::query()->where('name', 'دمشق')->value('id')
                ?? Governorate::query()->orderBy('sort_order')->value('id'),
            'address' => $address,
            'owner_name' => 'Wafa Review',
            'phone' => $phone,
            'contact_phone' => $contactPhone,
        ]);

        $package = Package::query()->where('is_active', true)->orderBy('sort_order')->value('id');

        $merchant->forceFill([
            'clerk_user_id' => $clerkUserId,
            'status' => MerchantStatus::Active,
            'selected_package_id' => $package,
            // Nobody signs in to a demo shop; the PIN only completes registration.
            'pin_hash' => Hash::make(Str::random(32)),
        ])->save();

        // A long paid period keeps the demo shops active and in the directory.
        $merchant->subscriptionPeriods()->create([
            'package_id' => $package,
            'type' => SubscriptionPeriodType::Paid,
            'duration_months' => 12,
            'starts_at' => now(),
            'ends_at' => now()->addYears(10),
            'note' => 'Store review demo shop',
        ]);

        return $merchant;
    }

    private function card(Merchant $merchant, string $iconKey, CardColor $color, string $name, string $reward, int $stampsRequired): Card
    {
        $existing = Card::query()->where('merchant_id', $merchant->id)->where('name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $card = new Card([
            'icon_id' => Icon::query()->where('key', $iconKey)->value('id')
                ?? Icon::query()->orderBy('sort_order')->value('id'),
            'color' => $color,
            'name' => $name,
            'stamps_required' => $stampsRequired,
            'reward_description' => $reward,
        ]);

        $card->forceFill(['merchant_id' => $merchant->id])->save();

        return $card;
    }

    /**
     * One cycle per card and status, so a collected reward and the card
     * started after it sit side by side.
     */
    private function cycle(Card $card, Customer $customer, int $stamps, CardCycleStatus $status): CardCycle
    {
        $existing = CardCycle::query()
            ->where('card_id', $card->id)
            ->where('customer_id', $customer->id)
            ->where('status', $status)
            ->first();

        if ($existing) {
            return $existing;
        }

        $cycle = CardCycle::query()->create([
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
        ]);

        // A collected cycle happened a while ago; the rest are recent.
        $daysAgo = $status === CardCycleStatus::Redeemed ? 20 : $stamps;

        $cycle->forceFill([
            'stamps_count' => $stamps,
            'status' => $status,
            'completed_at' => $status === CardCycleStatus::Collecting ? null : now()->subDays($status === CardCycleStatus::Redeemed ? 12 : 0),
            'redeemed_at' => $status === CardCycleStatus::Redeemed ? now()->subDays(10) : null,
        ])->save();

        foreach (range(1, $stamps) as $index) {
            Stamp::query()->create([
                'card_cycle_id' => $cycle->id,
                'card_id' => $card->id,
                'customer_id' => $customer->id,
                'merchant_id' => $card->merchant_id,
                'method' => StampMethod::Qr,
                'client_uuid' => (string) Str::uuid(),
                'stamped_at' => now()->subDays($daysAgo - $index + 1),
            ]);
        }

        return $cycle;
    }

    private function campaign(Merchant $merchant, string $title, string $body): Campaign
    {
        $existing = Campaign::query()->where('merchant_id', $merchant->id)->where('title', $title)->first();

        if ($existing) {
            return $existing;
        }

        $campaign = new Campaign(['title' => $title, 'body' => $body]);
        $campaign->forceFill([
            'merchant_id' => $merchant->id,
            'recipients_count' => 1,
            'sent_at' => now()->subDays(2),
        ])->save();

        return $campaign;
    }

    /**
     * Filled once, oldest first, through the app's own notifications so each
     * entry has the exact shape the app reads. The account has no phones
     * registered, so no push goes anywhere.
     *
     * @param  list<object>  $read
     * @param  list<object>  $unread
     */
    private function inbox(Customer $reviewer, array $read, array $unread): void
    {
        if ($reviewer->notifications()->exists()) {
            return;
        }

        foreach ($read as $notification) {
            $reviewer->notify($notification);
        }

        $reviewer->notifications()->update(['read_at' => now()]);

        foreach ($unread as $notification) {
            $reviewer->notify($notification);
        }
    }
}
