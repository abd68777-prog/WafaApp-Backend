<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Icon;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The shop's cards (contract §5.8): listed for the scan screen without the
 * PIN, published and suspended behind it, never edited.
 */
class CardTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    public function test_the_list_is_open_to_the_cashier_and_shows_active_cards_first_with_their_counts(): void
    {
        $merchant = $this->merchant();
        $suspended = Card::factory()->for($merchant)->suspended()->create();
        $active = Card::factory()->for($merchant)->create();
        CardCycle::factory()->for($active)->create();
        CardCycle::factory()->for($active)->rewardReady()->create();
        CardCycle::factory()->for($active)->redeemed()->create();
        Card::factory()->create();

        $response = $this->withToken($this->clerkToken(self::CLERK_USER))->getJson('/api/v1/merchant/cards');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.active_customers', 2)
            ->assertJsonPath('data.0.rewards_ready', 1)
            ->assertJsonPath('data.1.id', $suspended->id)
            ->assertJsonPath('data.1.status', 'suspended');

        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->getJson('/api/v1/merchant/cards?status=active')
            ->assertJsonCount(1, 'data');
    }

    public function test_publishing_a_card_needs_the_pin(): void
    {
        $this->merchant();

        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->postJson('/api/v1/merchant/cards', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');

        $this->assertDatabaseCount('cards', 0);
    }

    public function test_a_card_is_published_with_the_chosen_icon(): void
    {
        $merchant = $this->merchant();
        $icon = Icon::factory()->create(['key' => 'coffee-cup']);

        $response = $this->publish($merchant, $this->payload(['icon_id' => $icon->id]));

        $response->assertCreated()
            ->assertJsonPath('data.name', 'بطاقة القهوة')
            ->assertJsonPath('data.stamps_required', 8)
            ->assertJsonPath('data.icon.key', 'coffee-cup')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.active_customers', 0);

        $this->assertDatabaseHas('cards', [
            'merchant_id' => $merchant->id,
            'name' => 'بطاقة القهوة',
            'status' => CardStatus::Active->value,
        ]);
    }

    public function test_the_package_limit_counts_active_cards_only(): void
    {
        $merchant = $this->merchant(cardsLimit: 1);
        Card::factory()->for($merchant)->suspended()->create();

        $this->publish($merchant, $this->payload())->assertCreated();

        $this->publish($merchant, $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CARDS_LIMIT_REACHED')
            ->assertJsonPath('error.details.cards_limit', 1);

        $this->assertSame(1, $merchant->cards()->where('status', CardStatus::Active)->count());
    }

    public function test_an_expired_shop_cannot_publish_cards(): void
    {
        $merchant = $this->merchant(state: 'expired');

        $this->publish($merchant, $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MERCHANT_STATUS_BLOCKS_ACTION')
            ->assertJsonPath('error.details', ['status' => 'EXPIRED', 'action' => 'cards']);
    }

    public function test_the_stamp_count_must_stay_within_the_configured_range(): void
    {
        Setting::write('card_stamps_max', 10);
        $merchant = $this->merchant();

        $this->publish($merchant, $this->payload(['stamps_required' => 11]))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.stamps_required', ['max']);

        $this->publish($merchant, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'stamps_required', 'reward_description', 'icon_id'], 'error.details.fields');
    }

    public function test_suspending_is_final_and_repeating_it_changes_nothing(): void
    {
        $this->freezeTime();
        $merchant = $this->merchant();
        $card = Card::factory()->for($merchant)->create();

        $this->suspend($merchant, $card)->assertOk()->assertJsonPath('data.status', 'suspended');
        $suspendedAt = $card->fresh()->suspended_at;

        $this->travel(1)->hour();
        $this->suspend($merchant, $card)->assertOk();

        $this->assertEquals($suspendedAt, $card->fresh()->suspended_at);
    }

    public function test_a_card_of_another_shop_cannot_be_suspended(): void
    {
        $merchant = $this->merchant();
        $othersCard = Card::factory()->create();

        $this->suspend($merchant, $othersCard)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame(CardStatus::Active, $othersCard->fresh()->status);
    }

    private function merchant(int $cardsLimit = 2, ?string $state = null): Merchant
    {
        $factory = Merchant::factory()->onPackage(Package::factory()->create(['cards_limit' => $cardsLimit]));

        return ($state ? $factory->{$state}() : $factory)->create(['clerk_user_id' => self::CLERK_USER]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publish(Merchant $merchant, array $payload): TestResponse
    {
        return $this->withPin($merchant)->postJson('/api/v1/merchant/cards', $payload);
    }

    private function suspend(Merchant $merchant, Card $card): TestResponse
    {
        return $this->withPin($merchant)->postJson("/api/v1/merchant/cards/{$card->id}/suspend");
    }

    private function withPin(Merchant $merchant): self
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($merchant)['token']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'بطاقة القهوة',
            'stamps_required' => 8,
            'reward_description' => 'قهوة مجانية',
            'terms' => null,
            'icon_id' => $overrides['icon_id'] ?? Icon::factory()->create()->id,
            ...$overrides,
        ];
    }
}
