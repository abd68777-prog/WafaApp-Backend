<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Notifications\StampAdded;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A customer switches one shop's offers off and on again (contract §4.4).
 * Campaigns from that shop stop; stamps and rewards are still notified.
 */
class MerchantMuteTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Merchant $merchant;

    private CardCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->consented()->create();
        $this->merchant = Merchant::factory()->onPackage()->create(['clerk_user_id' => 'user_shop']);
        $this->cycle = CardCycle::factory()->for(Card::factory()->for($this->merchant))->for($this->customer)->create(['stamps_count' => 1]);
    }

    public function test_muting_and_unmuting_show_on_my_cards_and_repeat_harmlessly(): void
    {
        $this->asCustomer()->putJson($this->mutePath())->assertNoContent();
        $this->asCustomer()->putJson($this->mutePath())->assertNoContent();

        $this->assertSame(1, $this->customer->merchantMutes()->count());
        $this->asCustomer()->getJson('/api/v1/customer/cards')->assertJsonPath('data.0.merchant_muted', true);

        $this->asCustomer()->deleteJson($this->mutePath())->assertNoContent();
        $this->asCustomer()->deleteJson($this->mutePath())->assertNoContent();

        $this->asCustomer()->getJson('/api/v1/customer/cards')->assertJsonPath('data.0.merchant_muted', false);
    }

    public function test_a_muted_shop_sends_no_campaigns_but_stamps_are_still_notified(): void
    {
        $this->asCustomer()->putJson($this->mutePath())->assertNoContent();

        $this->withToken($this->clerkToken('user_shop'))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token'])
            ->postJson('/api/v1/merchant/campaigns', ['title' => 'عرض', 'body' => 'نص'])
            ->assertCreated()
            ->assertJsonPath('data.recipients_count', 0);

        $this->customer->notify(new StampAdded($this->cycle, $this->cycle->card));

        $this->assertSame(['stamp_added'], $this->customer->notifications()->pluck('type')->all());
    }

    public function test_an_unknown_or_unregistered_shop_is_not_found(): void
    {
        $this->asCustomer()->putJson('/api/v1/customer/merchants/999999/mute')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $registering = Merchant::factory()->awaitingPackage()->create();
        $this->asCustomer()->putJson("/api/v1/customer/merchants/{$registering->id}/mute")->assertNotFound();
    }

    public function test_a_customer_who_has_not_finished_the_profile_cannot_mute(): void
    {
        $incomplete = Customer::factory()->withoutProfile()->consented()->create();

        $this->withToken($incomplete->createToken('phone', ['customer'])->plainTextToken)
            ->putJson($this->mutePath())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PROFILE_INCOMPLETE');
    }

    private function mutePath(): string
    {
        return "/api/v1/customer/merchants/{$this->merchant->id}/mute";
    }

    private function asCustomer(): self
    {
        return $this->withToken($this->customer->createToken('phone', ['customer'])->plainTextToken);
    }
}
