<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The payments reviewer's queue and decisions (requirements §5.2). Only that
 * role decides; not even the Super Admin, who sets the prices.
 */
class PaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private Package $medium;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local', ['serve' => true]);
        $this->travelTo('2026-10-01 12:00:00');
        Setting::write('grace_days', 3);
        Setting::write('payment_review_sla_hours', 24);
        $this->medium = Package::factory()->create(['cards_limit' => 2]);
        AdminUser::factory()->create(['clerk_user_id' => 'user_reviewer', 'role' => AdminRole::PaymentsReviewer]);
        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
    }

    public function test_approving_continues_the_subscription_and_tells_the_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial()->for($merchant)->for($this->medium)->create(['ends_at' => '2026-10-05 12:00:00']);
        $payment = Payment::factory()->for($merchant)->for($this->medium)->create(['duration_months' => 3]);

        $this->as('user_reviewer')->postJson("/api/v1/admin/payments/{$payment->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.merchant.status', 'ACTIVE');

        $period = $payment->fresh()->subscriptionPeriod;
        $this->assertSame('2026-10-05 12:00:00', $period->starts_at->toDateTimeString());
        $this->assertSame('2027-01-05 12:00:00', $period->ends_at->toDateTimeString());
        $this->assertSame('2027-01-08 12:00:00', $period->grace_ends_at->toDateTimeString());
        $this->assertSame(MerchantStatus::Active, $merchant->fresh()->status);

        $entry = $merchant->notifications()->sole();
        $this->assertSame('payment_approved', $entry->type);
        $this->assertSame('قُبلت دفعتك، واشتراكك فعّال حتى 2027/01/05.', $entry->data['body']);
        $this->assertSame('payment.approved', AuditLog::query()->sole()->action);
    }

    public function test_approving_brings_an_expired_shop_back_from_today(): void
    {
        $merchant = Merchant::factory()->expired()->create();
        $payment = Payment::factory()->for($merchant)->for($this->medium)->create();

        $this->as('user_reviewer')->postJson("/api/v1/admin/payments/{$payment->id}/approve")->assertOk();

        $period = $payment->fresh()->subscriptionPeriod;
        $this->assertTrue($period->starts_at->equalTo(now()));
        $this->assertSame(MerchantStatus::Active, $merchant->fresh()->status);
    }

    public function test_rejecting_names_the_reason_to_the_merchant(): void
    {
        $payment = Payment::factory()->create();

        $this->as('user_reviewer')->postJson("/api/v1/admin/payments/{$payment->id}/reject", ['reason' => 'amount_short'])
            ->assertOk()
            ->assertJsonPath('data.status', 'REJECTED')
            ->assertJsonPath('data.rejection_reason', 'amount_short');

        $this->assertSame('لم تُقبل دفعتك: المبلغ ناقص.', $payment->merchant->notifications()->sole()->data['body']);
        $this->assertNull($payment->fresh()->subscription_period_id);

        $this->as('user_reviewer')->postJson("/api/v1/admin/payments/{$payment->id}/reject", ['reason' => 'nope'])
            ->assertUnprocessable();
    }

    public function test_a_payment_is_decided_once(): void
    {
        $payment = Payment::factory()->approved()->create();

        $this->as('user_reviewer')->postJson("/api/v1/admin/payments/{$payment->id}/approve")
            ->assertConflict()
            ->assertJsonPath('error.code', 'PAYMENT_NOT_PENDING')
            ->assertJsonPath('error.details.status', 'APPROVED');

        $this->assertSame(0, SubscriptionPeriod::query()->count());
    }

    public function test_the_queue_is_oldest_first_and_flags_late_reviews_and_reused_references(): void
    {
        $late = Payment::factory()->create(['reference' => 'TX1', 'created_at' => now()->subHours(30)]);
        $fresh = Payment::factory()->create(['reference' => 'TX2']);
        Payment::factory()->rejected()->create(['reference' => 'TX1']);

        $this->as('user_reviewer')->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $late->id)
            ->assertJsonPath('data.0.overdue', true)
            ->assertJsonPath('data.0.duplicate_reference', true)
            ->assertJsonPath('data.0.merchant.id', $late->merchant_id)
            ->assertJsonPath('data.1.id', $fresh->id)
            ->assertJsonPath('data.1.overdue', false)
            ->assertJsonPath('data.1.duplicate_reference', false);

        $this->as('user_reviewer')->getJson('/api/v1/admin/payments?status=REJECTED')->assertJsonCount(1, 'data');
        $this->as('user_reviewer')->getJson("/api/v1/admin/payments/{$fresh->id}")->assertJsonPath('data.reference', 'TX2');
    }

    public function test_only_the_payments_reviewer_decides(): void
    {
        $payment = Payment::factory()->create();

        $this->as('user_owner')->postJson("/api/v1/admin/payments/{$payment->id}/approve")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    private function as(string $clerkUserId): self
    {
        return $this->withToken($this->clerkToken($clerkUserId));
    }
}
