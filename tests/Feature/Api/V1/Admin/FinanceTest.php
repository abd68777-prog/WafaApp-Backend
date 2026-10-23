<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\PaymentMethod;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment history and the financial reports (requirements §6.6), for the
 * Super Admin and the payments reviewer only.
 */
class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
        AdminUser::factory()->superAdmin()->create(['clerk_user_id' => 'user_owner']);
    }

    public function test_the_report_counts_approved_payments_by_month_package_and_method(): void
    {
        $basic = Package::factory()->create(['name' => 'الأساسية']);
        $plus = Package::factory()->create(['name' => 'المميزة']);

        $this->approved($basic, '2026-09-10 10:00:00', '10.00', '130000.00', PaymentMethod::SyriatelCash);
        $this->approved($plus, '2026-10-02 10:00:00', '25.50', '331500.00', PaymentMethod::Transfer);
        $this->approved($basic, '2026-10-03 10:00:00', '10.00', '130000.00', PaymentMethod::SyriatelCash);
        // Approved at 22:30 UTC on 31 October: 1 November in Damascus, outside the range.
        $this->approved($basic, '2026-10-31 22:30:00', '10.00', '130000.00', PaymentMethod::SyriatelCash);
        // Pending and rejected payments are not income.
        Payment::factory()->create(['package_id' => $basic->id]);
        Payment::factory()->rejected()->create(['package_id' => $basic->id]);

        $this->asOwner()->getJson('/api/v1/admin/finance/report?from=2026-09-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.totals', ['count' => 3, 'price_usd' => '45.50', 'amount_syp' => '591500.00'])
            ->assertJsonPath('data.by_month', [
                ['month' => '2026-09', 'count' => 1, 'price_usd' => '10.00', 'amount_syp' => '130000.00'],
                ['month' => '2026-10', 'count' => 2, 'price_usd' => '35.50', 'amount_syp' => '461500.00'],
            ])
            ->assertJsonPath('data.by_package.0.package.name', 'الأساسية')
            ->assertJsonPath('data.by_package.0.count', 2)
            ->assertJsonPath('data.by_package.1.price_usd', '25.50')
            ->assertJsonPath('data.by_method', [
                ['method' => 'syriatel_cash', 'count' => 2, 'price_usd' => '20.00', 'amount_syp' => '260000.00'],
                ['method' => 'transfer', 'count' => 1, 'price_usd' => '25.50', 'amount_syp' => '331500.00'],
            ]);

        // Without dates: this month so far.
        $this->asOwner()->getJson('/api/v1/admin/finance/report')
            ->assertJsonPath('data.from', '2026-10-01')
            ->assertJsonPath('data.to', '2026-10-15')
            ->assertJsonPath('data.totals.count', 2);
    }

    public function test_the_history_lists_every_payment_with_filters(): void
    {
        $merchant = Merchant::factory()->create();
        $approved = Payment::factory()->approved()->for($merchant)->create(['created_at' => '2026-10-01 10:00:00']);
        Payment::factory()->rejected()->for($merchant)->create(['created_at' => '2026-10-05 10:00:00']);
        $pending = Payment::factory()->for($merchant)->create();

        $this->asOwner()->getJson('/api/v1/admin/finance/payments')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->asOwner()->getJson('/api/v1/admin/finance/payments?status=APPROVED')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);

        $this->asOwner()->getJson('/api/v1/admin/finance/payments?from=2026-10-01&to=2026-10-04')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);

        $this->asOwner()->getJson('/api/v1/admin/finance/payments?from=2026-10-05&to=2026-10-01')->assertUnprocessable();
    }

    public function test_only_the_super_admin_and_the_payments_reviewer_see_the_money(): void
    {
        AdminUser::factory()->paymentsReviewer()->create(['clerk_user_id' => 'user_reviewer']);
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);

        $this->withToken($this->clerkToken('user_reviewer'))->getJson('/api/v1/admin/finance/report')->assertOk();

        foreach (['user_admin', 'user_support'] as $user) {
            $this->withToken($this->clerkToken($user))->getJson('/api/v1/admin/finance/report')->assertForbidden();
            $this->withToken($this->clerkToken($user))->getJson('/api/v1/admin/finance/payments')->assertForbidden();
        }
    }

    private function approved(Package $package, string $reviewedAt, string $usd, string $syp, PaymentMethod $method): Payment
    {
        return Payment::factory()->approved()->create([
            'package_id' => $package->id,
            'price_usd' => $usd,
            'amount_syp' => $syp,
            'method' => $method,
            'reviewed_at' => $reviewedAt,
        ]);
    }

    private function asOwner(): self
    {
        return $this->withToken($this->clerkToken('user_owner'));
    }
}
