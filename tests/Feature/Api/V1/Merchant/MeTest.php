<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\CardStatus;
use App\Models\Campaign;
use App\Models\Card;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The merchant app's first request: which screen to open, what the
 * subscription allows now, the banner, and the package usage (contract
 * MerchantMe).
 */
class MeTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_merchant_1';

    public function test_a_new_clerk_user_is_sent_to_the_business_step(): void
    {
        $response = $this->withToken($this->clerkToken(self::CLERK_USER, ['email' => 'Shop@Example.com']))
            ->getJson('/api/v1/merchant/me');

        $response->assertOk()->assertExactJson(['data' => [
            'registration_step' => 'business',
            'email' => 'shop@example.com',
            'merchant' => null,
            'subscription' => null,
            'usage' => null,
        ]]);
    }

    public function test_me_records_the_visit_and_follows_a_changed_clerk_email(): void
    {
        $this->freezeTime();
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER, 'email' => 'old@example.com']);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER, ['email' => 'New@Example.com']))
            ->getJson('/api/v1/merchant/me');

        $response->assertOk()->assertJsonPath('data.merchant.email', 'new@example.com');

        $merchant->refresh();
        $this->assertSame('new@example.com', $merchant->email);
        $this->assertSame(now()->toDateTimeString(), $merchant->last_login_at->toDateTimeString());
    }

    public function test_an_active_trial_allows_everything_and_counts_usage(): void
    {
        $this->travelTo('2026-09-24 09:00:00');
        $package = Package::factory()->create(['cards_limit' => 2, 'weekly_campaigns_limit' => 3]);
        $merchant = Merchant::factory()->onPackage($package)->create(['clerk_user_id' => self::CLERK_USER]);
        Card::factory()->for($merchant)->create();
        Card::factory()->for($merchant)->create(['status' => CardStatus::Suspended]);
        // Thursday 24 September: the week started on Saturday the 19th.
        Campaign::factory()->for($merchant)->create(['sent_at' => '2026-09-20 10:00:00']);
        Campaign::factory()->for($merchant)->create(['sent_at' => '2026-09-18 10:00:00']);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/v1/merchant/me');

        $response->assertOk()
            ->assertJsonPath('data.registration_step', 'done')
            ->assertJsonPath('data.subscription.status', 'TRIAL')
            ->assertJsonPath('data.subscription.package.cards_limit', 2)
            ->assertJsonPath('data.subscription.days_remaining', 14)
            ->assertJsonPath('data.subscription.trial_used', true)
            ->assertJsonPath('data.subscription.banner', null)
            ->assertJsonPath('data.subscription.pending_payment', null)
            ->assertJsonPath('data.subscription.capabilities', [
                'stamps' => true,
                'new_customers' => true,
                'campaigns' => true,
                'redemptions' => true,
                'create_cards' => true,
                'directory_visible' => true,
            ])
            ->assertJsonPath('data.usage', [
                'active_cards' => 1,
                'cards_limit' => 2,
                'campaigns_used_this_week' => 1,
                'weekly_campaigns_limit' => 3,
                // Saturday 26 September, midnight in Damascus (UTC+3).
                'campaigns_resets_at' => '2026-09-25T21:00:00Z',
            ]);
    }

    public function test_a_trial_in_its_last_days_shows_the_ending_banner(): void
    {
        $this->travelTo('2026-09-24 09:00:00');
        $merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        SubscriptionPeriod::factory()->for($merchant)->trial(2)->create();

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/v1/merchant/me');

        $response->assertOk()->assertJsonPath('data.subscription.banner', [
            'code' => 'TRIAL_ENDING',
            'level' => 'warning',
            'params' => ['days_remaining' => 2, 'ends_at' => '2026-09-26T09:00:00Z'],
        ]);
    }

    public function test_an_expired_shop_may_only_hand_over_rewards(): void
    {
        Merchant::factory()->expired()->onPackage(trial: false)->create(['clerk_user_id' => self::CLERK_USER]);

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/v1/merchant/me');

        $response->assertOk()
            ->assertJsonPath('data.subscription.days_remaining', null)
            ->assertJsonPath('data.subscription.banner.code', 'EXPIRED')
            ->assertJsonPath('data.subscription.capabilities', [
                'stamps' => false,
                'new_customers' => false,
                'campaigns' => false,
                'redemptions' => true,
                'create_cards' => false,
                'directory_visible' => false,
            ]);
    }
}
