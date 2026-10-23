<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use App\Models\AdminUser;
use App\Models\DeletionRequest;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's first screen (requirements §6.6): open to every role, with
 * the money figures only for those who see the financials.
 */
class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_shops_by_status_trials_ending_and_what_needs_attention(): void
    {
        AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);

        Merchant::factory()->awaitingPackage()->create();
        $endingSoon = Merchant::factory()->create(['business_name' => 'فرن الشام']);
        SubscriptionPeriod::factory()->trial(3)->for($endingSoon)->create();
        $endingLater = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial(10)->for($endingLater)->create();
        // A shop has one payment pending at most.
        [$late, $onTime, $reused] = Merchant::factory()->active()->count(3)->create();

        // Late, waiting for 30 hours against a 24-hour promise.
        Payment::factory()->for($late)->create(['created_at' => now()->subHours(30), 'reference' => 'TX1']);
        Payment::factory()->for($onTime)->create(['reference' => 'TX2']);
        // The same receipt as an earlier, approved payment.
        Payment::factory()->for($reused)->approved()->create(['reference' => 'TX9']);
        Payment::factory()->for($reused)->create(['reference' => 'TX9']);

        $leaving = Merchant::factory()->create(['status' => 'PENDING_DELETION']);
        DeletionRequest::factory()->create([
            'subject_type' => DeletionSubjectType::Merchant,
            'subject_id' => $leaving->id,
            'source' => DeletionSource::Support,
            'requested_at' => now()->subDay(),
            'executes_at' => now()->addDays(29),
        ]);
        DeletionRequest::factory()->create(['requested_at' => now()->subDays(20)]);

        $this->withToken($this->clerkToken('user_support'))->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.merchants.registering', 1)
            ->assertJsonPath('data.merchants.by_status.TRIAL', 2)
            ->assertJsonPath('data.merchants.by_status.ACTIVE', 3)
            ->assertJsonPath('data.merchants.total', 7)
            ->assertJsonPath('data.merchants.by_status.PENDING_DELETION', 1)
            ->assertJsonPath('data.trials_ending_this_week.count', 1)
            ->assertJsonPath('data.trials_ending_this_week.merchants.0.business_name', 'فرن الشام')
            ->assertJsonPath('data.alerts.payments_pending', 3)
            ->assertJsonPath('data.alerts.payments_overdue', 1)
            ->assertJsonPath('data.alerts.payments_duplicate_reference', 1)
            ->assertJsonPath('data.alerts.deletion_requests_new', 1)
            ->assertJsonPath('data.alerts.merchants_pending_deletion', 1)
            ->assertJsonPath('data.finance', null);
    }

    public function test_the_payments_reviewer_also_sees_this_months_income(): void
    {
        AdminUser::factory()->paymentsReviewer()->create(['clerk_user_id' => 'user_reviewer']);
        Payment::factory()->approved()->create(['price_usd' => '10.00', 'amount_syp' => '130000.00']);
        Payment::factory()->approved()->create(['price_usd' => '25.50', 'amount_syp' => '331500.00']);
        Payment::factory()->approved()->create(['reviewed_at' => '2026-09-30 12:00:00']);

        $this->withToken($this->clerkToken('user_reviewer'))->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.finance', [
                'month' => '2026-10',
                'count' => 2,
                'price_usd' => '35.50',
                'amount_syp' => '461500.00',
            ]);
    }
}
