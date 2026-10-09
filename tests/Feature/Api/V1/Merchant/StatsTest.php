<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\StampMethod;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Stamp;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The statistics tab (contract §5.5): totals, per card and a daily series,
 * in Damascus days and the Saturday week, cancelled stamps left out.
 */
class StatsTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    private Card $coffee;

    private Card $tea;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 7 October, 01:30 in Damascus (still Tuesday the 6th in UTC).
        $this->travelTo('2026-10-06 22:30:00');
        $this->merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $this->coffee = Card::factory()->for($this->merchant)->create(['name' => 'القهوة']);
        $this->tea = Card::factory()->for($this->merchant)->create(['name' => 'الشاي']);
    }

    public function test_totals_per_card_and_the_daily_series_count_in_damascus_days(): void
    {
        $sara = Customer::factory()->create();
        $omar = Customer::factory()->pending()->create();
        $saraCoffee = CardCycle::factory()->for($this->coffee)->for($sara)->rewardReady()->create();
        $omarCoffee = CardCycle::factory()->for($this->coffee)->for($omar)->create();
        CardCycle::factory()->for($this->tea)->for($sara)->redeemed()->create(['redeemed_at' => '2026-10-05 10:00:00']);

        // Today in Damascus (after 21:00 UTC on the 6th).
        $this->stamp($saraCoffee, '2026-10-06 21:30:00');
        $this->stamp($omarCoffee, '2026-10-06 22:00:00', StampMethod::Phone);
        // Earlier this week (Saturday the 3rd, Damascus) and cancelled ones.
        $this->stamp($saraCoffee, '2026-10-03 08:00:00');
        $this->stamp($saraCoffee, '2026-10-06 21:45:00', cancelled: true);
        // Last week: in the series, not in the week.
        $this->stamp($saraCoffee, '2026-10-02 08:00:00');
        // Another shop.
        Stamp::factory()->create(['stamped_at' => '2026-10-06 22:00:00']);

        $response = $this->stats('');

        $response->assertOk()->assertJsonPath('data.totals', [
            'customers_total' => 2,
            'stamps_today' => 2,
            'stamps_this_week' => 3,
            'stamps_via_phone_today' => 1,
            'rewards_ready' => 1,
            'rewards_redeemed_total' => 1,
        ]);

        $response->assertJsonPath('data.per_card.0.card_id', $this->coffee->id)
            ->assertJsonPath('data.per_card.0.card_name', 'القهوة')
            ->assertJsonPath('data.per_card.0.stamps_this_week', 3)
            ->assertJsonPath('data.per_card.1.card_id', $this->tea->id)
            ->assertJsonPath('data.per_card.1.customers_total', 1)
            ->assertJsonPath('data.per_card.1.rewards_redeemed_total', 1)
            ->assertJsonPath('data.per_card.1.stamps_today', 0);

        $daily = $response->json('data.daily');
        $this->assertCount(7, $daily);
        $this->assertSame('2026-10-01', $daily[0]['date']);
        $this->assertSame(['date' => '2026-10-07', 'stamps' => 2, 'stamps_via_phone' => 1, 'rewards_redeemed' => 0], $daily[6]);
        $this->assertSame(1, $daily[1]['stamps'], '2 October');
        $this->assertSame(1, $daily[4]['rewards_redeemed'], '5 October');
    }

    public function test_one_card_and_thirty_days(): void
    {
        $cycle = CardCycle::factory()->for($this->tea)->for(Customer::factory()->create())->create();
        $this->stamp($cycle, '2026-09-10 08:00:00');
        $this->stamp(CardCycle::factory()->for($this->coffee)->for(Customer::factory()->create())->create(), '2026-10-06 21:30:00');

        $response = $this->stats("?card_id={$this->tea->id}&days=30")->assertOk();

        $this->assertCount(30, $response->json('data.daily'));
        $this->assertSame('2026-09-08', $response->json('data.daily.0.date'));
        $this->assertSame(1, $response->json('data.daily.2.stamps'));
        $response->assertJsonCount(1, 'data.per_card')->assertJsonPath('data.totals.stamps_today', 0);
    }

    public function test_another_shops_card_and_odd_day_counts_are_refused(): void
    {
        $foreign = Card::factory()->create();

        $this->stats("?card_id={$foreign->id}")->assertNotFound();
        $this->stats('?days=14')->assertUnprocessable()->assertJsonPath('error.details.fields.days', ['format']);
    }

    public function test_statistics_are_behind_the_pin(): void
    {
        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->getJson('/api/v1/merchant/stats')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    private function stamp(CardCycle $cycle, string $at, StampMethod $method = StampMethod::Qr, bool $cancelled = false): void
    {
        Stamp::factory()->for($cycle)->create([
            'card_id' => $cycle->card_id,
            'customer_id' => $cycle->customer_id,
            'merchant_id' => $cycle->merchant_id,
            'method' => $method,
            'stamped_at' => $at,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
    }

    private function stats(string $query): TestResponse
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token'])
            ->getJson('/api/v1/merchant/stats'.$query);
    }
}
