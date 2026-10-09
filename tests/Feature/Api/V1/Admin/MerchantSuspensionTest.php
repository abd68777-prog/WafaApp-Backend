<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\MerchantStatus;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Card;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Holding a shop for fraud or a breach, and letting it go (requirements §3.2,
 * transitions 7 and 8), always with a reason on the record.
 */
class MerchantSuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
    }

    public function test_a_suspended_shop_stops_stamping_and_leaves_the_directory(): void
    {
        $merchant = Merchant::factory()->active()->create();
        SubscriptionPeriod::factory()->for($merchant)->create();
        Card::factory()->for($merchant)->create();

        $this->act($merchant, 'suspend', 'طوابع وهمية')
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED')
            ->assertJsonPath('data.capabilities.stamps', false)
            ->assertJsonPath('data.capabilities.redemptions', true);

        $merchant->refresh();
        $this->assertSame(MerchantStatus::Suspended, $merchant->status);
        $this->assertSame('طوابع وهمية', $merchant->suspension_reason);

        $log = AuditLog::query()->sole();
        $this->assertSame('merchant.suspended', $log->action);
        $this->assertSame(['status' => 'ACTIVE'], $log->before);
        $this->assertSame('طوابع وهمية', $log->after['reason']);

        $this->app['auth']->forgetGuards();
        $customer = Customer::factory()->consented()->create();
        $this->withToken($customer->createToken('phone', ['customer'])->plainTextToken)
            ->getJson('/api/v1/customer/directory')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reactivating_returns_the_status_the_dates_call_for(): void
    {
        $running = Merchant::factory()->suspended()->create();
        SubscriptionPeriod::factory()->for($running)->create();

        $this->act($running, 'reactivate', 'تم التحقق')
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
        $this->assertNull($running->fresh()->suspended_at);
        $this->assertNull($running->fresh()->suspension_reason);

        $ranOut = Merchant::factory()->suspended()->create();
        SubscriptionPeriod::factory()->for($ranOut)->create([
            'starts_at' => '2026-08-01 12:00:00',
            'ends_at' => '2026-09-01 12:00:00',
            'grace_ends_at' => '2026-09-04 12:00:00',
        ]);

        $this->act($ranOut, 'reactivate', 'تم التحقق')->assertJsonPath('data.status', 'EXPIRED');

        $this->assertSame(['ACTIVE', 'EXPIRED'], AuditLog::query()->orderBy('id')->get()->pluck('after.status')->all());
    }

    public function test_a_shop_in_the_wrong_status_answers_409(): void
    {
        $this->act(Merchant::factory()->suspended()->create(), 'suspend', 'مرة تانية')
            ->assertConflict()
            ->assertJsonPath('error.code', 'MERCHANT_STATUS_CONFLICT')
            ->assertJsonPath('error.details.status', 'SUSPENDED');

        $this->act(Merchant::factory()->active()->create(), 'reactivate', 'مو موقوف')
            ->assertConflict()
            ->assertJsonPath('error.details.status', 'ACTIVE');

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_reason_is_required_and_support_cannot_suspend(): void
    {
        $merchant = Merchant::factory()->active()->create();

        $this->withToken($this->clerkToken('user_admin'))
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/suspend")
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['reason' => ['required']]);

        AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);
        $this->withToken($this->clerkToken('user_support'))
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/suspend", ['reason' => 'سبب'])
            ->assertForbidden();
    }

    private function act(Merchant $merchant, string $action, string $reason): TestResponse
    {
        return $this->withToken($this->clerkToken('user_admin'))
            ->postJson("/api/v1/admin/merchants/{$merchant->id}/{$action}", ['reason' => $reason]);
    }
}
