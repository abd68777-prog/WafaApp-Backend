<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\MerchantMute;
use App\Models\Stamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "My cards" (contract §4.3): ready rewards first, a zero-progress card after
 * a reward, and a suspended card gone once its reward is handed over.
 */
class CardTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-27 10:00:00');
        $this->customer = Customer::factory()->consented()->create();
    }

    public function test_ready_rewards_come_first_then_cards_by_their_latest_stamp(): void
    {
        $older = $this->collecting(stampedAt: now()->subDays(3));
        $newer = $this->collecting(stampedAt: now()->subDay());
        $ready = CardCycle::factory()->for($this->customer)->rewardReady()->create(['stamps_count' => 5]);
        $this->collecting(customer: Customer::factory()->create());

        $response = $this->withToken($this->token())->getJson('/api/v1/customer/cards');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.cycle.id', $ready->id)
            ->assertJsonPath('data.0.cycle.status', 'REWARD_READY')
            ->assertJsonPath('data.1.card.id', $newer->card_id)
            ->assertJsonPath('data.1.last_stamp_at', '2026-09-26T10:00:00Z')
            ->assertJsonPath('data.2.card.id', $older->card_id)
            ->assertJsonStructure(['data' => [['card' => ['icon' => ['key']], 'merchant' => ['business_name', 'business_type', 'governorate']]]]);
    }

    public function test_after_a_reward_an_active_card_shows_zero_progress_and_a_suspended_one_disappears(): void
    {
        $active = Card::factory()->create();
        CardCycle::factory()->for($active)->for($this->customer)->redeemed()->count(2)->create();
        $suspended = Card::factory()->suspended()->create();
        CardCycle::factory()->for($suspended)->for($this->customer)->redeemed()->create();

        $response = $this->withToken($this->token())->getJson('/api/v1/customer/cards');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.card.id', $active->id)
            ->assertJsonPath('data.0.cycle', ['id' => null, 'stamps_count' => 0, 'status' => 'COLLECTING', 'completed_at' => null])
            ->assertJsonPath('data.0.completed_cycles_count', 2);
    }

    public function test_a_muted_shop_is_flagged_on_its_cards(): void
    {
        $cycle = $this->collecting();
        MerchantMute::factory()->create(['customer_id' => $this->customer->id, 'merchant_id' => $cycle->merchant_id]);

        $this->withToken($this->token())->getJson('/api/v1/customer/cards')->assertJsonPath('data.0.merchant_muted', true);
    }

    public function test_the_card_detail_lists_the_current_stamps_without_the_cancelled_ones(): void
    {
        $cycle = $this->collecting(stampedAt: now()->subDays(2));
        Stamp::factory()->for($cycle)->create(['stamped_at' => now()->subDay(), 'method' => 'phone']);
        Stamp::factory()->for($cycle)->cancelled()->create();

        $response = $this->withToken($this->token())->getJson("/api/v1/customer/cards/{$cycle->card_id}");

        $response->assertOk()
            ->assertJsonPath('data.cycle.id', $cycle->id)
            ->assertJsonPath('data.stamps', [
                ['stamped_at' => '2026-09-25T10:00:00Z', 'method' => 'qr'],
                ['stamped_at' => '2026-09-26T10:00:00Z', 'method' => 'phone'],
            ])
            ->assertJsonStructure(['data' => ['merchant_address']]);
    }

    public function test_a_card_the_customer_never_collected_on_is_not_found(): void
    {
        $someoneElses = $this->collecting(customer: Customer::factory()->create());

        $this->withToken($this->token())
            ->getJson("/api/v1/customer/cards/{$someoneElses->card_id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    private function collecting(?Customer $customer = null, ?\DateTimeInterface $stampedAt = null): CardCycle
    {
        $cycle = CardCycle::factory()->for($customer ?? $this->customer)->create(['stamps_count' => 1]);
        Stamp::factory()->for($cycle)->create(['stamped_at' => $stampedAt ?? now()]);

        return $cycle;
    }

    private function token(): string
    {
        return $this->customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
