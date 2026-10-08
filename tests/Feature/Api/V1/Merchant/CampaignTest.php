<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\MerchantStatus;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantMute;
use App\Models\Package;
use App\Models\Setting;
use App\Services\Merchant\PinUnlockToken;
use Database\Factories\CustomerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Campaigns (contract §5.7, requirements §7.3): text only, to the shop's
 * registered customers who did not switch its offers off, a few per week.
 */
class CampaignTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 7 October, 15:00 in Damascus; the week started on Saturday the 3rd.
        $this->travelTo('2026-10-07 12:00:00');
        $package = Package::factory()->create(['weekly_campaigns_limit' => 1]);
        $this->merchant = Merchant::factory()->onPackage($package)->create(['clerk_user_id' => self::CLERK_USER, 'business_name' => 'كافيه الياسمين']);
        $this->card = Card::factory()->for($this->merchant)->create();
    }

    public function test_a_campaign_reaches_the_shops_customers_except_those_who_switched_it_off(): void
    {
        $regular = $this->customerOfTheShop();
        $longAgo = $this->customerOfTheShop(redeemed: true);
        $mutedAll = $this->customerOfTheShop(['campaigns_muted' => true]);
        $mutedShop = $this->customerOfTheShop();
        MerchantMute::factory()->create(['customer_id' => $mutedShop->id, 'merchant_id' => $this->merchant->id]);
        $pending = $this->customerOfTheShop([], Customer::factory()->pending());
        $elsewhere = Customer::factory()->create();
        CardCycle::factory()->for($elsewhere)->create();

        $response = $this->send(['title' => 'عرض الخميس', 'body' => 'القهوة التانية مجاناً كل يوم خميس.']);

        $response->assertCreated()->assertJson(['data' => [
            'title' => 'عرض الخميس',
            'body' => 'القهوة التانية مجاناً كل يوم خميس.',
            'recipients_count' => 2,
            'sent_at' => '2026-10-07T12:00:00Z',
        ]]);

        foreach ([$regular, $longAgo] as $customer) {
            $entry = $customer->notifications()->sole();
            $this->assertSame('campaign', $entry->type);
            $this->assertSame('كافيه الياسمين: عرض الخميس', $entry->data['title']);
            $this->assertSame('القهوة التانية مجاناً كل يوم خميس.', $entry->data['body']);
            $this->assertSame(['merchant_id' => $this->merchant->id, 'campaign_id' => $response->json('data.id')], $entry->data['data']);
        }

        foreach ([$mutedAll, $mutedShop, $pending, $elsewhere] as $customer) {
            $this->assertSame(0, $customer->notifications()->count());
        }
    }

    public function test_the_package_allows_so_many_campaigns_a_week(): void
    {
        $this->send(['title' => 'الأول', 'body' => 'نص'])->assertCreated();

        $this->send(['title' => 'التاني', 'body' => 'نص'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CAMPAIGN_WEEKLY_LIMIT_REACHED')
            ->assertJsonPath('error.details', ['weekly_limit' => 1, 'resets_at' => '2026-10-09T21:00:00Z']);

        // Saturday 00:00 in Damascus starts a new week.
        $this->travelTo('2026-10-09 21:00:00');
        $this->send(['title' => 'التاني', 'body' => 'نص'])->assertCreated();
    }

    public function test_links_are_refused(): void
    {
        $this->send(['title' => 'زورونا على wafa-offers.com', 'body' => 'نص'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CAMPAIGN_CONTAINS_LINK')
            ->assertJsonPath('error.details.field', 'title');

        $this->send(['title' => 'عرض', 'body' => 'التفاصيل: https://bit.ly/x'])->assertJsonPath('error.details.field', 'body');

        $this->assertSame(0, $this->merchant->campaigns()->count());
    }

    public function test_an_expired_shop_sends_no_campaigns(): void
    {
        $this->merchant->forceFill(['status' => MerchantStatus::Expired])->save();

        $this->send(['title' => 'عرض', 'body' => 'نص'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details', ['status' => 'EXPIRED', 'action' => 'campaigns']);
    }

    public function test_the_lengths_come_from_the_settings(): void
    {
        Setting::write('campaign_title_max', 10);

        $this->send(['title' => str_repeat('ع', 11), 'body' => str_repeat('ن', 301)])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['title' => ['max'], 'body' => ['max']]);

        $this->send([])->assertJsonPath('error.details.fields', ['title' => ['required'], 'body' => ['required']]);
    }

    public function test_the_list_comes_newest_first_with_the_weekly_counter(): void
    {
        $older = $this->merchant->campaigns()->create(['title' => 'قديمة', 'body' => 'نص']);
        $older->forceFill(['sent_at' => '2026-09-20 10:00:00', 'recipients_count' => 5])->save();
        $this->send(['title' => 'جديدة', 'body' => 'نص'])->assertCreated();

        $this->withPin()->getJson('/api/v1/merchant/campaigns')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'جديدة')
            ->assertJsonPath('data.1.recipients_count', 5)
            ->assertJsonPath('meta', [
                'next_cursor' => null,
                'campaigns_used_this_week' => 1,
                'weekly_campaigns_limit' => 1,
                'campaigns_resets_at' => '2026-10-09T21:00:00Z',
            ]);
    }

    public function test_campaigns_are_behind_the_pin(): void
    {
        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->postJson('/api/v1/merchant/campaigns', ['title' => 'عرض', 'body' => 'نص'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customerOfTheShop(array $attributes = [], ?CustomerFactory $factory = null, bool $redeemed = false): Customer
    {
        $customer = ($factory ?? Customer::factory())->create($attributes);
        $cycle = CardCycle::factory()->for($this->card)->for($customer);
        ($redeemed ? $cycle->redeemed() : $cycle)->create();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(array $body): TestResponse
    {
        return $this->withPin()->postJson('/api/v1/merchant/campaigns', $body);
    }

    private function withPin(): self
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token']);
    }
}
