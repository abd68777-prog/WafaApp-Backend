<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\MerchantStatus;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\DeletionRequest;
use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use App\Notifications\MerchantLeaving;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A shop leaves Wafa at its own request (requirements §3.6): 30 days that can
 * be cancelled, customers with ready rewards told to collect them, then the
 * sign-in and personal details erased while the business name and its cards
 * stay.
 */
class MerchantDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
        config(['services.clerk.secret_key' => 'sk_test_123', 'services.clerk.api_url' => 'https://api.clerk.test/v1']);
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
    }

    public function test_a_shop_waits_thirty_days_and_customers_with_ready_rewards_are_told(): void
    {
        Notification::fake();
        $merchant = Merchant::factory()->active()->create(['business_name' => 'كافيه الياسمين']);
        SubscriptionPeriod::factory()->for($merchant)->create();
        $card = Card::factory()->for($merchant)->create();
        $ready = CardCycle::factory()->rewardReady()->create(['card_id' => $card->id]);
        $collecting = CardCycle::factory()->create(['card_id' => $card->id]);

        $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'support', 'note' => 'اتصل صاحب المحل'])
            ->assertCreated()
            ->assertJsonPath('data.subject_type', 'merchant')
            ->assertJsonPath('data.subject_name', 'كافيه الياسمين')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.executes_at', '2026-10-31T12:00:00Z');

        $this->assertSame(MerchantStatus::PendingDeletion, $merchant->fresh()->status);
        $this->assertFalse(MerchantStatus::PendingDeletion->canCollectStamps());
        $this->assertTrue(MerchantStatus::PendingDeletion->canRedeemRewards());

        Notification::assertSentTo($ready->customer, MerchantLeaving::class, function (MerchantLeaving $notification) use ($ready): bool {
            $inbox = $notification->toArray($ready->customer);

            return $inbox['title'] === 'كافيه الياسمين سيغادر وفاء'
                && $inbox['body'] === 'استلم هديتك قبل 2026/10/31.'
                && $inbox['data']['cycle_id'] === $ready->id
                && $inbox['data']['leaves_at'] === '2026-10-31T12:00:00Z';
        });
        Notification::assertNotSentTo($collecting->customer, MerchantLeaving::class);

        $this->asAdmin()->getJson("/api/v1/admin/merchants/{$merchant->id}")
            ->assertJsonPath('data.deletion.executes_at', '2026-10-31T12:00:00Z')
            ->assertJsonPath('data.stats.rewards_ready', 1);

        $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'support'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_STATUS_CONFLICT');
    }

    public function test_cancelling_returns_the_shop_to_where_it_was(): void
    {
        $running = Merchant::factory()->active()->create();
        SubscriptionPeriod::factory()->for($running)->create();
        $held = Merchant::factory()->suspended()->create();
        SubscriptionPeriod::factory()->for($held)->create();

        foreach ([$running, $held] as $merchant) {
            $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'email'])->assertCreated();
        }

        $runningRequest = DeletionRequest::query()->where('subject_id', $running->id)->sole();
        $this->asAdmin()->postJson("/api/v1/admin/deletion-requests/{$runningRequest->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(MerchantStatus::Active, $running->fresh()->status);

        $heldRequest = DeletionRequest::query()->where('subject_id', $held->id)->sole();
        $this->asAdmin()->postJson("/api/v1/admin/deletion-requests/{$heldRequest->id}/cancel")->assertOk();
        $this->assertSame(MerchantStatus::Suspended, $held->fresh()->status);

        $this->asAdmin()->postJson("/api/v1/admin/deletion-requests/{$heldRequest->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('error.code', 'DELETION_REQUEST_NOT_PENDING');

        $this->assertSame(
            ['merchant.deletion_scheduled', 'merchant.deletion_scheduled', 'merchant.deletion_cancelled', 'merchant.deletion_cancelled'],
            AuditLog::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_after_thirty_days_the_sign_in_and_personal_details_are_erased(): void
    {
        Http::fake(['api.clerk.test/v1/users/user_shop' => Http::response(['deleted' => true])]);
        $merchant = Merchant::factory()->active()->create(['clerk_user_id' => 'user_shop', 'business_name' => 'فرن الشام']);
        SubscriptionPeriod::factory()->for($merchant)->create();
        $card = Card::factory()->for($merchant)->create();
        $merchant->deviceTokens()->create(['token' => 'fcm-1', 'platform' => 'android', 'app' => 'merchant']);
        $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'support'])->assertCreated();

        $this->travel(29)->days();
        $this->artisan('merchants:execute-deletions')->assertSuccessful();
        $this->assertSame(MerchantStatus::PendingDeletion, $merchant->fresh()->status);
        Http::assertNothingSent();

        $this->travel(1)->days();
        $this->artisan('merchants:execute-deletions')->assertSuccessful();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request->url() === 'https://api.clerk.test/v1/users/user_shop');
        $merchant->refresh();
        $this->assertSame(MerchantStatus::Deleted, $merchant->status);
        $this->assertNull($merchant->clerk_user_id);
        $this->assertNull($merchant->owner_name);
        $this->assertNull($merchant->phone);
        $this->assertNull($merchant->email);
        $this->assertNull($merchant->pin_hash);
        $this->assertSame('فرن الشام', $merchant->business_name);
        $this->assertTrue($card->exists && Card::query()->whereKey($card->id)->exists());
        $this->assertSame(0, $merchant->deviceTokens()->count());
        $this->assertNotNull(DeletionRequest::query()->sole()->executed_at);
    }

    public function test_when_clerk_cannot_be_reached_nothing_is_erased_and_it_is_tried_again(): void
    {
        // Out of reach the first night; already gone in Clerk the next, which
        // counts as done.
        Http::fake(['api.clerk.test/*' => Http::sequence()
            ->push(['errors' => []], 503)
            ->push(['errors' => []], 404)]);
        $merchant = Merchant::factory()->active()->create(['clerk_user_id' => 'user_shop']);
        SubscriptionPeriod::factory()->for($merchant)->create();
        $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'support'])->assertCreated();

        $this->travel(30)->days();
        $this->artisan('merchants:execute-deletions')->assertFailed();

        $this->assertSame('user_shop', $merchant->fresh()->clerk_user_id);
        $this->assertNull(DeletionRequest::query()->sole()->executed_at);

        $this->artisan('merchants:execute-deletions')->assertSuccessful();
        $this->assertSame(MerchantStatus::Deleted, $merchant->fresh()->status);
    }

    public function test_the_list_filters_by_status_and_support_cannot_delete(): void
    {
        $merchant = Merchant::factory()->active()->create();
        SubscriptionPeriod::factory()->for($merchant)->create();
        $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'app'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['source' => ['format']]);
        $this->asAdmin()->postJson("/api/v1/admin/merchants/{$merchant->id}/deletion", ['source' => 'support'])->assertCreated();

        $this->asAdmin()->getJson('/api/v1/admin/deletion-requests?status=pending')->assertJsonCount(1, 'data');
        $this->asAdmin()->getJson('/api/v1/admin/deletion-requests?status=executed')->assertJsonCount(0, 'data');

        AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);
        $this->withToken($this->clerkToken('user_support'))->getJson('/api/v1/admin/deletion-requests')->assertForbidden();
    }

    private function asAdmin(): self
    {
        return $this->withToken($this->clerkToken('user_admin'));
    }
}
