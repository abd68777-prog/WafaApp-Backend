<?php

namespace Tests\Feature\Api\V1;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Notifications\StampAdded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The in-app inbox of both apps: newest first by cursor, and read marks that
 * touch only the owner's own notifications.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inbox_pages_newest_first_by_cursor(): void
    {
        $this->travelTo('2026-09-27 10:00:00');
        $customer = Customer::factory()->consented()->create();
        $cycle = CardCycle::factory()->for($customer)->create(['stamps_count' => 1]);

        foreach (range(1, 3) as $ignored) {
            $this->travel(1)->minutes();
            $customer->notify(new StampAdded($cycle, $cycle->card));
        }

        $first = $this->withToken($this->customerToken($customer))->getJson('/api/v1/customer/notifications?limit=2');

        $first->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'stamp_added')
            ->assertJsonPath('data.0.created_at', '2026-09-27T10:03:00Z')
            ->assertJsonPath('data.0.data', ['merchant_id' => $cycle->merchant_id, 'card_id' => $cycle->card_id, 'cycle_id' => $cycle->id])
            ->assertJsonPath('data.0.read_at', null);

        $second = $this->withToken($this->customerToken($customer))
            ->getJson('/api/v1/customer/notifications?limit=2&cursor='.$first->json('meta.next_cursor'));

        $second->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.created_at', '2026-09-27T10:01:00Z')
            ->assertJsonPath('meta.next_cursor', null);
    }

    public function test_marking_read_touches_only_the_owners_notifications(): void
    {
        $customer = Customer::factory()->consented()->create();
        $other = Customer::factory()->consented()->create();
        $card = Card::factory()->create();
        $customer->notify(new StampAdded(CardCycle::factory()->for($card)->for($customer)->create(), $card));
        $other->notify(new StampAdded(CardCycle::factory()->for($card)->for($other)->create(), $card));
        $othersNotification = $other->notifications()->sole();

        $this->withToken($this->customerToken($customer))
            ->postJson("/api/v1/customer/notifications/{$othersNotification->id}/read")
            ->assertNotFound();

        $this->withToken($this->customerToken($customer))
            ->postJson("/api/v1/customer/notifications/{$customer->notifications()->sole()->id}/read")
            ->assertNoContent();

        $this->assertNotNull($customer->notifications()->sole()->read_at);
        $this->assertNull($othersNotification->fresh()->read_at);
    }

    public function test_a_merchant_marks_the_whole_inbox_read(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $merchant->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'trial_ending',
            'data' => ['title' => 'التجربة تنتهي', 'body' => 'تنتهي تجربتك المجانية بعد 3 أيام.', 'data' => []],
        ]);

        $this->withToken($this->clerkToken('user_shop'))->postJson('/api/v1/merchant/notifications/read-all')->assertNoContent();

        $this->assertSame(0, $merchant->unreadNotifications()->count());
    }

    private function customerToken(Customer $customer): string
    {
        return $customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
