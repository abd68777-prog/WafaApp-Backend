<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\CardCycleStatus;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CardCycle;
use App\Models\Stamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A wrong stamp is corrected from the dashboard, with a written reason
 * (requirements §2.3): marked, never deleted, and never after the reward was
 * handed over.
 */
class StampCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_the_stamp_that_completed_a_card_puts_it_back_to_collecting(): void
    {
        $admin = AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        $cycle = CardCycle::factory()->rewardReady()->create(['stamps_count' => 3]);
        $cycle->card->forceFill(['stamps_required' => 3])->save();
        $stamp = Stamp::factory()->for($cycle)->create();

        $response = $this->cancel($stamp, 'user_admin', 'أضيف للزبون الخطأ');

        $response->assertOk()
            ->assertJsonPath('data.cancel_reason', 'أضيف للزبون الخطأ')
            ->assertJsonPath('data.cycle.stamps_count', 2)
            ->assertJsonPath('data.cycle.status', 'COLLECTING');

        $stamp->refresh();
        $this->assertModelExists($stamp);
        $this->assertNotNull($stamp->cancelled_at);
        $this->assertSame($admin->id, $stamp->cancelled_by_admin_id);
        $this->assertNull($cycle->fresh()->completed_at);

        $log = AuditLog::query()->sole();
        $this->assertSame('stamp.cancelled', $log->action);
        $this->assertSame(['stamps_count' => 3, 'status' => 'REWARD_READY'], $log->before);
    }

    public function test_no_stamp_is_cancelled_after_its_reward_was_handed_over(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        $cycle = CardCycle::factory()->redeemed()->create(['stamps_count' => 3]);
        $stamp = Stamp::factory()->for($cycle)->create();

        $this->cancel($stamp, 'user_admin', 'متأخر')
            ->assertConflict()
            ->assertJsonPath('error.code', 'REWARD_ALREADY_REDEEMED');

        $this->assertNull($stamp->fresh()->cancelled_at);
        $this->assertSame(CardCycleStatus::Redeemed, $cycle->fresh()->status);
    }

    public function test_a_reason_is_required(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        $stamp = Stamp::factory()->create();

        $this->cancel($stamp, 'user_admin', '')
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.reason', ['required']);
    }

    public function test_support_may_not_cancel_stamps(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_support', 'role' => AdminRole::Support]);
        $stamp = Stamp::factory()->create();

        $this->cancel($stamp, 'user_support', 'خطأ')->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertNull($stamp->fresh()->cancelled_at);
    }

    private function cancel(Stamp $stamp, string $clerkUserId, string $reason): TestResponse
    {
        return $this->withToken($this->clerkToken($clerkUserId))
            ->postJson("/api/v1/admin/stamps/{$stamp->id}/cancel", ['reason' => $reason]);
    }
}
