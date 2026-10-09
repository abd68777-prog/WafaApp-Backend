<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\StampMethod;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Stamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customers screen (requirements §5.4): found by full number, shown
 * masked, with every reveal and birthdate correction on the record.
 */
class AdminCustomersTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
        AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);
        $this->customer = Customer::factory()->create(['phone' => '+963933111222', 'name' => 'سارة', 'birthdate' => '1995-04-20']);
    }

    public function test_a_customer_is_found_by_the_full_number_in_any_form_and_shown_masked(): void
    {
        foreach (['0933111222', '+963933111222', '963933111222'] as $typed) {
            $this->asSupport()->getJson('/api/v1/admin/customers?phone='.urlencode($typed))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $this->customer->id)
                ->assertJsonPath('data.0.phone_masked', '0933***222')
                ->assertJsonMissingPath('data.0.phone');
        }

        $this->asSupport()->getJson('/api/v1/admin/customers?phone=0944000000')->assertOk()->assertJsonCount(0, 'data');
        $this->asSupport()->getJson('/api/v1/admin/customers?phone=0933')
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['phone' => ['format']]);
    }

    public function test_the_customer_page_lists_cycles_and_a_cycle_lists_its_stamps(): void
    {
        $cycle = CardCycle::factory()->for($this->customer)->create(['stamps_count' => 1]);
        Stamp::factory()->for($cycle)->create(['method' => StampMethod::Phone]);
        Stamp::factory()->for($cycle)->cancelled()->create();
        $pending = Customer::factory()->pending()->create();

        $this->asSupport()->getJson("/api/v1/admin/customers/{$this->customer->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'سارة')
            ->assertJsonPath('data.birthdate', '1995-04-20')
            ->assertJsonPath('data.registered', true)
            ->assertJsonCount(1, 'data.cycles')
            ->assertJsonPath('data.cycles.0.id', $cycle->id)
            ->assertJsonPath('data.cycles.0.merchant.id', $cycle->merchant_id);

        $this->asSupport()->getJson("/api/v1/admin/customers/{$pending->id}")
            ->assertOk()
            ->assertJsonPath('data.registered', false)
            ->assertJsonPath('data.name', null);

        $this->asSupport()->getJson("/api/v1/admin/card-cycles/{$cycle->id}")
            ->assertOk()
            ->assertJsonPath('data.customer_id', $this->customer->id)
            ->assertJsonCount(2, 'data.stamps')
            ->assertJsonPath('data.stamps.0.method', 'phone')
            ->assertJsonPath('data.stamps.1.cancel_reason', 'Added to the wrong customer');
    }

    public function test_revealing_the_full_number_is_recorded(): void
    {
        $this->asSupport()->postJson("/api/v1/admin/customers/{$this->customer->id}/reveal-phone")
            ->assertOk()
            ->assertJsonPath('data.phone', '+963933111222');

        $log = AuditLog::query()->sole();
        $this->assertSame('customer.phone_revealed', $log->action);
        $this->assertSame($this->customer->id, $log->subject_id);
    }

    public function test_support_corrects_a_birthdate_with_a_reason_but_never_under_13(): void
    {
        $this->asSupport()->patchJson("/api/v1/admin/customers/{$this->customer->id}", [
            'birthdate' => '1995-05-20',
            'reason' => 'تحققنا من الهوية',
        ])->assertOk()->assertJsonPath('data.birthdate', '1995-05-20');

        $log = AuditLog::query()->sole();
        $this->assertSame('customer.birthdate_updated', $log->action);
        $this->assertSame(['birthdate' => '1995-04-20'], $log->before);

        $this->asSupport()->patchJson("/api/v1/admin/customers/{$this->customer->id}", [
            'birthdate' => '2020-01-01',
            'reason' => 'تحققنا من الهوية',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'UNDER_AGE');

        $this->assertSame('1995-05-20', $this->customer->fresh()->birthdate->toDateString());
    }

    public function test_the_payments_reviewer_sees_no_customers_and_deleted_ones_are_gone(): void
    {
        AdminUser::factory()->paymentsReviewer()->create(['clerk_user_id' => 'user_reviewer']);

        $this->withToken($this->clerkToken('user_reviewer'))
            ->getJson("/api/v1/admin/customers/{$this->customer->id}")
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->customer->delete();

        $this->asSupport()->getJson("/api/v1/admin/customers/{$this->customer->id}")->assertNotFound();
    }

    private function asSupport(): self
    {
        return $this->withToken($this->clerkToken('user_support'));
    }
}
