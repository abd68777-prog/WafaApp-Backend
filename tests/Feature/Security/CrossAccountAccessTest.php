<?php

namespace Tests\Feature\Security;

use App\Enums\CardStatus;
use App\Models\Campaign;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * One account can never read or change another's data by guessing an id.
 * What belongs to someone else answers 404, the same as what does not exist,
 * so the answer gives nothing away. Stamping and handing over rewards across
 * shops are covered in StampingTest.
 */
class CrossAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $shop;

    private Merchant $otherShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
        $this->shop = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->otherShop = Merchant::factory()->create(['clerk_user_id' => 'user_other_shop']);
    }

    public function test_a_shop_cannot_suspend_another_shops_card(): void
    {
        $card = Card::factory()->for($this->otherShop)->create();

        $this->asShop()->postJson("/api/v1/merchant/cards/{$card->id}/suspend")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame(CardStatus::Active, $card->fresh()->status);
    }

    public function test_a_shop_cannot_greet_a_customer_who_is_not_its_own(): void
    {
        $theirCustomer = Customer::factory()->consented()->create(['birthdate' => '1995-10-15']);
        CardCycle::factory()->create(['card_id' => Card::factory()->for($this->otherShop)->create()->id, 'customer_id' => $theirCustomer->id]);

        $this->asShop()->postJson("/api/v1/merchant/customers/{$theirCustomer->id}/birthday-greeting", ['message' => 'كل عام وأنت بخير'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_lists_show_only_the_shops_own_rows(): void
    {
        Payment::factory()->for($this->otherShop)->create();
        Campaign::factory()->for($this->otherShop)->create();
        Card::factory()->for($this->otherShop)->create();

        $this->asShop()->getJson('/api/v1/merchant/payments')->assertOk()->assertJsonCount(0, 'data');
        $this->asShop()->getJson('/api/v1/merchant/campaigns')->assertOk()->assertJsonCount(0, 'data');
        $this->asShop()->getJson('/api/v1/merchant/cards')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_nobody_reads_or_marks_someone_elses_notification(): void
    {
        $shopsNote = $this->notificationFor($this->otherShop);
        $customer = Customer::factory()->consented()->create();
        $otherCustomer = Customer::factory()->consented()->create();
        $customersNote = $this->notificationFor($otherCustomer);

        $this->asShop()->postJson("/api/v1/merchant/notifications/{$shopsNote->id}/read")->assertNotFound();
        $this->asShop()->getJson('/api/v1/merchant/notifications')->assertJsonCount(0, 'data');

        $token = $customer->createToken('phone', ['customer'])->plainTextToken;
        $this->withToken($token)->postJson("/api/v1/customer/notifications/{$customersNote->id}/read")->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/customer/notifications/read-all')->assertNoContent();

        $this->assertNull($shopsNote->fresh()->read_at);
        $this->assertNull($customersNote->fresh()->read_at);
    }

    public function test_a_customer_cannot_open_a_card_they_never_joined_or_remove_anothers_phone(): void
    {
        $customer = Customer::factory()->consented()->create();
        $otherCustomer = Customer::factory()->consented()->create();
        $card = Card::factory()->for($this->shop)->create();
        CardCycle::factory()->create(['card_id' => $card->id, 'customer_id' => $otherCustomer->id]);
        $otherCustomer->deviceTokens()->create(['token' => 'fcm-other', 'platform' => 'android', 'app' => 'customer']);

        $token = $customer->createToken('phone', ['customer'])->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/customer/cards/{$card->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->withToken($token)->deleteJson('/api/v1/customer/devices/fcm-other')->assertNoContent();
        $this->assertSame(1, $otherCustomer->deviceTokens()->count());
    }

    public function test_a_pin_token_opens_only_the_shop_it_was_issued_to(): void
    {
        $otherShopsPin = app(PinUnlockToken::class)->issue($this->otherShop)['token'];

        $this->withToken($this->clerkToken('user_shop'))
            ->withHeader('X-Pin-Token', $otherShopsPin)
            ->getJson('/api/v1/merchant/stats')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    private function asShop(): self
    {
        return $this->withToken($this->clerkToken('user_shop'))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->shop)['token']);
    }

    private function notificationFor(Merchant|Customer $owner): DatabaseNotification
    {
        return $owner->notifications()->create([
            'id' => (string) Str::uuid7(),
            'type' => 'stamp_added',
            'data' => ['title' => 'x', 'body' => 'y', 'data' => []],
        ]);
    }
}
