<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\MerchantStatus;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * More free days for a shop that never paid (requirements §5.3), granted by
 * the Super Admin with a reason, and on the record.
 */
class TrialExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
    }

    public function test_a_running_trial_is_extended_from_its_end(): void
    {
        $merchant = Merchant::factory()->create();
        $trial = SubscriptionPeriod::factory()->trial()->for($merchant)->create(['ends_at' => '2026-10-05 12:00:00']);

        $this->extend($merchant, 7)
            ->assertOk()
            ->assertJsonPath('data.status', 'TRIAL')
            ->assertJsonPath('data.current_period.ends_at', '2026-10-12T12:00:00Z')
            ->assertJsonPath('data.days_remaining', 11);

        $this->assertSame('2026-10-12 12:00:00', $trial->fresh()->ends_at->toDateTimeString());

        $log = AuditLog::query()->sole();
        $this->assertSame('trial.extended', $log->action);
        $this->assertSame(['trial_ends_at' => '2026-10-05T12:00:00Z'], $log->before);
        $this->assertSame('عميل مهم، طلب وقت إضافي', $log->after['reason']);
    }

    public function test_an_ended_trial_is_extended_from_today_and_the_shop_is_back_on_trial(): void
    {
        $merchant = Merchant::factory()->expired()->create();
        SubscriptionPeriod::factory()->trial()->for($merchant)->create(['starts_at' => '2026-09-01 12:00:00', 'ends_at' => '2026-09-15 12:00:00']);

        $this->extend($merchant, 10)->assertOk()->assertJsonPath('data.current_period.ends_at', '2026-10-11T12:00:00Z');

        $this->assertSame(MerchantStatus::Trial, $merchant->fresh()->status);
    }

    public function test_only_a_shop_that_never_paid_and_is_not_held_can_be_extended(): void
    {
        $paid = Merchant::factory()->active()->create();
        SubscriptionPeriod::factory()->trial()->for($paid)->create(['ends_at' => '2026-09-01 12:00:00']);
        SubscriptionPeriod::factory()->for($paid)->create();
        $this->extend($paid, 7)->assertUnprocessable()
            ->assertJsonPath('error.code', 'TRIAL_NOT_EXTENDABLE')
            ->assertJsonPath('error.details.reason', 'paid');

        $noTrial = Merchant::factory()->expired()->create();
        $this->extend($noTrial, 7)->assertJsonPath('error.details.reason', 'no_trial');

        $suspended = Merchant::factory()->suspended()->create();
        SubscriptionPeriod::factory()->trial()->for($suspended)->create();
        $this->extend($suspended, 7)->assertJsonPath('error.details.reason', 'status');

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_days_and_the_reason_are_required_and_only_the_super_admin_extends(): void
    {
        $merchant = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial()->for($merchant)->create();

        $this->withToken($this->clerkToken('user_owner'))
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/trial-extension", ['days' => 91])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['days' => ['max'], 'reason' => ['required']]);

        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        $this->withToken($this->clerkToken('user_admin'))
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/trial-extension", ['days' => 7, 'reason' => 'سبب'])
            ->assertForbidden();
    }

    private function extend(Merchant $merchant, int $days): TestResponse
    {
        return $this->withToken($this->clerkToken('user_owner'))
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/trial-extension", [
                'days' => $days,
                'reason' => 'عميل مهم، طلب وقت إضافي',
            ]);
    }
}
