<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\CardCycleStatus;
use App\Enums\CardStatus;
use App\Enums\MerchantStatus;
use App\Enums\StampMethod;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Stamp;
use App\Services\Customer\CustomerQrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The scan sequence (contract §5.3): preview, then one stamp per confirmation,
 * then the reward handed over once.
 */
class StampingTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-27 10:00:00');
        $this->merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $this->card = Card::factory()->for($this->merchant)->create(['stamps_required' => 3]);
    }

    // --- Preview ----------------------------------------------------------

    public function test_a_scanned_code_previews_the_registered_customer_without_writing_anything(): void
    {
        $customer = Customer::factory()->create(['name' => 'سارة', 'phone' => '+963933123456']);

        $response = $this->resolve(['qr' => $this->qrFor($customer)]);

        $response->assertOk()
            ->assertJsonPath('data.method', 'qr')
            ->assertJsonPath('data.action', 'stamp')
            ->assertJsonPath('data.blocked_reason', null)
            ->assertJsonPath('data.customer', [
                'id' => $customer->id,
                'kind' => 'registered',
                'name' => 'سارة',
                'phone_full' => null,
                'phone_masked' => '0933***456',
            ])
            ->assertJsonPath('data.cycle', ['id' => null, 'stamps_count' => 0, 'status' => 'COLLECTING', 'completed_at' => null])
            ->assertJsonPath('data.expires_at', '2026-09-27T10:03:00Z');

        $this->assertDatabaseCount('card_cycles', 0);
    }

    public function test_a_code_from_the_previous_window_is_accepted_and_an_older_one_is_expired(): void
    {
        $customer = Customer::factory()->create();

        $this->resolve(['qr' => $this->qrFor($customer, now()->subSeconds(60)->getTimestamp())])->assertOk();

        $this->resolve(['qr' => $this->qrFor($customer, now()->subSeconds(120)->getTimestamp())])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'QR_EXPIRED');
    }

    public function test_an_unreadable_or_unknown_code_is_invalid(): void
    {
        $customer = Customer::factory()->create();

        foreach (['hello', 'W1.'.$customer->qr_id.'.00000000', 'W1.AAAAAAAAAAAA.12345678'] as $qr) {
            $this->resolve(['qr' => $qr])->assertUnprocessable()->assertJsonPath('error.code', 'QR_INVALID');
        }
    }

    public function test_a_typed_number_nobody_registered_is_shown_in_full_and_nobody_is_created_yet(): void
    {
        $response = $this->resolve(['phone' => '0933 111 222']);

        $response->assertOk()
            ->assertJsonPath('data.method', 'phone')
            ->assertJsonPath('data.action', 'stamp')
            ->assertJsonPath('data.customer', [
                'id' => null,
                'kind' => 'new',
                'name' => null,
                'phone_full' => '+963933111222',
                'phone_masked' => null,
            ]);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_pending_customer_is_shown_by_the_full_number_typed(): void
    {
        $pending = Customer::factory()->pending()->create(['phone' => '+963933111222']);

        $this->resolve(['phone' => '+963933111222'])
            ->assertOk()
            ->assertJsonPath('data.customer.kind', 'pending')
            ->assertJsonPath('data.customer.id', $pending->id)
            ->assertJsonPath('data.customer.phone_full', '+963933111222');
    }

    public function test_a_card_of_another_shop_is_not_found(): void
    {
        $customer = Customer::factory()->create();

        $this->resolve(['qr' => $this->qrFor($customer)], Card::factory()->create())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_exactly_one_of_code_and_number_is_sent(): void
    {
        $this->resolve([])->assertUnprocessable()->assertJsonValidationErrors(['qr', 'phone'], 'error.details.fields');
        $this->resolve(['qr' => 'W1.x.1', 'phone' => '+963933111222'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['qr'], 'error.details.fields');
    }

    public function test_a_ready_reward_offers_the_hand_over_to_a_scanned_code_only(): void
    {
        $customer = Customer::factory()->create();
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->rewardReady()->create(['stamps_count' => 3]);

        $this->resolve(['qr' => $this->qrFor($customer)])
            ->assertOk()
            ->assertJsonPath('data.action', 'redeem')
            ->assertJsonPath('data.cycle.id', $cycle->id)
            ->assertJsonPath('data.cycle.status', 'REWARD_READY');

        $this->resolve(['phone' => $customer->phone])
            ->assertOk()
            ->assertJsonPath('data.action', 'none')
            ->assertJsonPath('data.blocked_reason.code', 'REDEEM_REQUIRES_QR');
    }

    public function test_the_preview_explains_why_no_stamp_can_be_added(): void
    {
        $customer = Customer::factory()->create();
        $this->stampedAt($customer, now()->subMinutes(20));

        $this->resolve(['qr' => $this->qrFor($customer)])
            ->assertOk()
            ->assertJsonPath('data.action', 'none')
            ->assertJsonPath('data.blocked_reason.code', 'STAMP_INTERVAL')
            ->assertJsonPath('data.blocked_reason.details', [
                'last_stamp_at' => '2026-09-27T09:40:00Z',
                'next_allowed_at' => '2026-09-27T10:40:00Z',
                'minutes_since_last' => 20,
                'minutes_remaining' => 40,
            ]);
    }

    public function test_a_suspended_card_takes_no_new_customer(): void
    {
        $this->card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();

        $this->resolve(['phone' => '+963933111222'])
            ->assertOk()
            ->assertJsonPath('data.action', 'none')
            ->assertJsonPath('data.blocked_reason.code', 'CARD_SUSPENDED');
    }

    public function test_an_expired_shop_stamps_nothing_but_still_sees_other_ready_rewards(): void
    {
        $this->merchant->forceFill(['status' => MerchantStatus::Expired])->save();
        $customer = Customer::factory()->create();
        $otherCard = Card::factory()->for($this->merchant)->create();
        $ready = CardCycle::factory()->for($otherCard)->for($customer)->rewardReady()->create();

        $this->resolve(['qr' => $this->qrFor($customer)])
            ->assertOk()
            ->assertJsonPath('data.action', 'none')
            ->assertJsonPath('data.blocked_reason.code', 'MERCHANT_STATUS_BLOCKS_ACTION')
            ->assertJsonPath('data.blocked_reason.details', ['status' => 'EXPIRED', 'action' => 'stamps'])
            ->assertJsonPath('data.other_ready_rewards.0.cycle_id', $ready->id)
            ->assertJsonPath('data.other_ready_rewards.0.card.id', $otherCard->id);
    }

    // --- Stamps -----------------------------------------------------------

    public function test_confirming_adds_one_stamp_opens_the_cycle_and_tells_the_customer(): void
    {
        $customer = Customer::factory()->create(['last_activity_at' => now()->subMonth()]);
        $uuid = (string) Str::uuid();

        $response = $this->stamp($this->scanToken(['qr' => $this->qrFor($customer)]), $uuid);

        $response->assertCreated()
            ->assertJsonPath('data.stamp.method', 'qr')
            ->assertJsonPath('data.stamp.stamped_at', '2026-09-27T10:00:00Z')
            ->assertJsonPath('data.cycle.stamps_count', 1)
            ->assertJsonPath('data.cycle.status', 'COLLECTING')
            ->assertJsonPath('data.customer.phone_full', null);

        $stamp = Stamp::query()->sole();
        $this->assertSame($uuid, $stamp->client_uuid);
        $this->assertSame($customer->id, $stamp->customer_id);
        $this->assertSame(1, $stamp->cardCycle->stamps_count);
        $this->assertTrue($customer->fresh()->last_activity_at->equalTo(now()));

        $notification = $customer->notifications()->sole();
        $this->assertSame('stamp_added', $notification->type);
        $this->assertSame("أُضيف لك طابع عند {$this->merchant->business_name}. صار لديك 1 من 3.", $notification->data['body']);
    }

    public function test_a_retried_confirmation_returns_the_original_stamp_without_adding_another(): void
    {
        $customer = Customer::factory()->create();
        $token = $this->scanToken(['qr' => $this->qrFor($customer)]);
        $uuid = (string) Str::uuid();

        $first = $this->stamp($token, $uuid)->assertCreated();
        $retry = $this->stamp($token, $uuid);

        $retry->assertOk()->assertJsonPath('data.stamp.id', $first->json('data.stamp.id'));
        $this->assertDatabaseCount('stamps', 1);
        $this->assertSame(1, $customer->notifications()->count());
    }

    public function test_a_new_number_becomes_a_pending_customer_when_the_stamp_is_confirmed(): void
    {
        $response = $this->stamp($this->scanToken(['phone' => '+963933111222']));

        $response->assertCreated()
            ->assertJsonPath('data.stamp.method', 'phone')
            ->assertJsonPath('data.customer.kind', 'pending')
            ->assertJsonPath('data.customer.phone_full', null)
            ->assertJsonPath('data.customer.phone_masked', '0933***222');

        $pending = Customer::query()->sole();
        $this->assertTrue($pending->isPending());
        $this->assertSame('+963933111222', $pending->phone);
        $this->assertSame(StampMethod::Phone, Stamp::query()->sole()->method);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_the_last_stamp_completes_the_card_and_tells_the_customer_the_reward_is_ready(): void
    {
        $customer = Customer::factory()->create();
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->create(['stamps_count' => 2]);

        $this->stamp($this->scanToken(['qr' => $this->qrFor($customer)]))
            ->assertCreated()
            ->assertJsonPath('data.cycle.id', $cycle->id)
            ->assertJsonPath('data.cycle.status', 'REWARD_READY')
            ->assertJsonPath('data.cycle.completed_at', '2026-09-27T10:00:00Z');

        $this->assertSame(CardCycleStatus::RewardReady, $cycle->fresh()->status);
        // Both in the same second: the inbox still lists them in order.
        $this->assertSame(['card_completed', 'stamp_added'], $customer->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->pluck('type')->all());

        // Worded for a reward handed over in this very visit, too.
        $completed = $customer->notifications()->where('type', 'card_completed')->sole();
        $this->assertSame('هديتك جاهزة!', $completed->data['title']);
        $this->assertSame(
            "اكتملت بطاقتك في {$this->merchant->business_name}. اعرض رمزك للكاشير لتستلم {$this->card->reward_description}.",
            $completed->data['body'],
        );
    }

    public function test_the_rules_are_checked_again_at_confirmation(): void
    {
        $customer = Customer::factory()->create();
        $token = $this->scanToken(['qr' => $this->qrFor($customer)]);

        // Another device stamped the same customer after this preview.
        $this->stampedAt($customer, now()->subMinutes(5));

        $this->stamp($token)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'STAMP_INTERVAL')
            ->assertJsonPath('error.details.minutes_remaining', 55);
        $this->assertDatabaseCount('stamps', 1);
    }

    public function test_a_completed_card_takes_no_stamp_until_the_reward_is_handed_over(): void
    {
        $customer = Customer::factory()->create();
        $token = $this->scanToken(['phone' => $customer->phone]);
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->rewardReady()->create(['stamps_count' => 3]);

        $this->stamp($token)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'REWARD_READY_REDEEM_FIRST')
            ->assertJsonPath('error.details.cycle_id', $cycle->id);
    }

    public function test_customers_already_collecting_can_finish_a_suspended_card(): void
    {
        $customer = Customer::factory()->create();
        CardCycle::factory()->for($this->card)->for($customer)->create(['stamps_count' => 1]);
        $this->card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();

        $this->stamp($this->scanToken(['qr' => $this->qrFor($customer)]))->assertCreated()->assertJsonPath('data.cycle.stamps_count', 2);
    }

    public function test_a_scan_token_lasts_three_minutes_and_only_for_the_shop_it_was_issued_to(): void
    {
        $customer = Customer::factory()->create();
        $token = $this->scanToken(['qr' => $this->qrFor($customer)]);

        Merchant::factory()->create(['clerk_user_id' => 'user_other_shop']);
        $this->withToken($this->clerkToken('user_other_shop'))
            ->postJson('/api/v1/merchant/stamps', ['scan_token' => $token, 'client_uuid' => (string) Str::uuid()])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SCAN_TOKEN_EXPIRED');

        $this->travel(181)->seconds();

        $this->stamp($token)->assertUnprocessable()->assertJsonPath('error.code', 'SCAN_TOKEN_EXPIRED');
        $this->assertDatabaseCount('stamps', 0);
    }

    // --- Rewards ----------------------------------------------------------

    public function test_a_reward_is_handed_over_once_and_the_second_device_is_told(): void
    {
        $customer = Customer::factory()->create();
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->rewardReady()->create(['stamps_count' => 3]);
        $token = $this->scanToken(['qr' => $this->qrFor($customer)]);

        $this->redeem($token, $cycle->id)
            ->assertOk()
            ->assertJsonPath('data.cycle_id', $cycle->id)
            ->assertJsonPath('data.redeemed_at', '2026-09-27T10:00:00Z')
            ->assertJsonPath('data.next_cycle_available', true);

        $this->redeem($token, $cycle->id)
            ->assertConflict()
            ->assertJsonPath('error.code', 'REWARD_ALREADY_REDEEMED')
            ->assertJsonPath('error.details', ['cycle_id' => $cycle->id, 'redeemed_at' => '2026-09-27T10:00:00Z']);

        $this->assertSame(CardCycleStatus::Redeemed, $cycle->fresh()->status);
        $this->assertSame(1, $customer->notifications()->where('type', 'reward_redeemed')->count());
    }

    public function test_the_next_stamp_after_a_reward_opens_a_new_cycle(): void
    {
        $customer = Customer::factory()->create();
        $old = CardCycle::factory()->for($this->card)->for($customer)->redeemed()->create(['stamps_count' => 3]);

        $this->stamp($this->scanToken(['qr' => $this->qrFor($customer)]))
            ->assertCreated()
            ->assertJsonPath('data.cycle.stamps_count', 1);

        $this->assertNotSame($old->id, Stamp::query()->sole()->card_cycle_id);
        $this->assertSame(3, $old->fresh()->stamps_count);
    }

    public function test_a_reward_cannot_be_handed_over_on_a_typed_number(): void
    {
        $customer = Customer::factory()->create();
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->rewardReady()->create();

        $this->redeem($this->scanToken(['phone' => $customer->phone]), $cycle->id)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'REDEEM_REQUIRES_QR');

        $this->assertSame(CardCycleStatus::RewardReady, $cycle->fresh()->status);
    }

    public function test_only_a_ready_cycle_of_the_scanned_customer_can_be_handed_over(): void
    {
        $customer = Customer::factory()->create();
        $token = $this->scanToken(['qr' => $this->qrFor($customer)]);
        $collecting = CardCycle::factory()->for($this->card)->for($customer)->create(['stamps_count' => 1]);
        $someoneElses = CardCycle::factory()->for($this->card)->rewardReady()->create();

        $this->redeem($token, $collecting->id)->assertUnprocessable()->assertJsonPath('error.code', 'NO_REWARD_READY');
        $this->redeem($token, $someoneElses->id)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame(CardCycleStatus::RewardReady, $someoneElses->fresh()->status);
    }

    public function test_an_expired_shop_still_hands_over_rewards_and_a_suspended_card_starts_no_new_cycle(): void
    {
        $this->merchant->forceFill(['status' => MerchantStatus::Expired])->save();
        $this->card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();
        $customer = Customer::factory()->create();
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->rewardReady()->create();

        $this->redeem($this->scanToken(['qr' => $this->qrFor($customer)]), $cycle->id)
            ->assertOk()
            ->assertJsonPath('data.next_cycle_available', false);
    }

    public function test_a_suspended_card_lets_current_customers_finish_and_then_leaves_their_list(): void
    {
        $customer = Customer::factory()->consented()->create();
        CardCycle::factory()->for($this->card)->for($customer)->create(['stamps_count' => 2]);
        $this->card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();

        // The customer already collecting gets the last stamp as usual.
        $preview = $this->resolve(['qr' => $this->qrFor($customer)])->assertOk()->assertJsonPath('data.action', 'stamp');
        $this->stamp($preview->json('data.scan_token'))->assertCreated()->assertJsonPath('data.cycle.status', 'REWARD_READY');

        // …and the reward, after which no new cycle can open.
        $preview = $this->resolve(['qr' => $this->qrFor($customer)])->assertOk()->assertJsonPath('data.action', 'redeem');
        $this->redeem($preview->json('data.scan_token'), $preview->json('data.cycle.id'))
            ->assertOk()
            ->assertJsonPath('data.next_cycle_available', false);

        $this->withToken($customer->createToken('phone', ['customer'])->plainTextToken)
            ->getJson('/api/v1/customer/cards')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // A newcomer is refused.
        $this->resolve(['qr' => $this->qrFor(Customer::factory()->create())])
            ->assertOk()
            ->assertJsonPath('data.action', 'none')
            ->assertJsonPath('data.blocked_reason.code', 'CARD_SUSPENDED');
    }

    // --- Helpers ----------------------------------------------------------

    /**
     * The code the customer app would show at `$timestamp`.
     */
    private function qrFor(Customer $customer, ?int $timestamp = null): string
    {
        $code = CustomerQrCode::codeAt($customer->qr_secret, $timestamp ?? now()->getTimestamp(), 60);

        return "W1.{$customer->qr_id}.{$code}";
    }

    private function stampedAt(Customer $customer, \DateTimeInterface $at): void
    {
        $cycle = CardCycle::factory()->for($this->card)->for($customer)->create(['stamps_count' => 1]);
        Stamp::factory()->for($cycle)->create(['stamped_at' => $at]);
    }

    /**
     * @param  array<string, string>  $identification
     */
    private function resolve(array $identification, ?Card $card = null): TestResponse
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/scan/resolve', [
            'card_id' => ($card ?? $this->card)->id,
            ...$identification,
        ]);
    }

    /**
     * @param  array<string, string>  $identification
     */
    private function scanToken(array $identification): string
    {
        return $this->resolve($identification)->assertOk()->json('data.scan_token');
    }

    private function stamp(string $scanToken, ?string $clientUuid = null): TestResponse
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/stamps', [
            'scan_token' => $scanToken,
            'client_uuid' => $clientUuid ?? (string) Str::uuid(),
        ]);
    }

    private function redeem(string $scanToken, int $cycleId): TestResponse
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))->postJson('/api/v1/merchant/redemptions', [
            'scan_token' => $scanToken,
            'cycle_id' => $cycleId,
        ]);
    }
}
