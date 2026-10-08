<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payment screen's details and the subscription timings, set by the
 * Super Admin (requirements §5.5).
 */
class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_updates_what_the_payment_screen_shows(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
        Setting::write('exchange_rate_syp', 13000);

        $this->withToken($this->clerkToken('user_owner'))->patchJson('/api/v1/admin/settings/billing', [
            'exchange_rate_syp' => 14500,
            'syriatel_cash_number' => '0933123456',
            'grace_days' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.exchange_rate_syp', '14500.00')
            ->assertJsonPath('data.syriatel_cash_number', '0933123456')
            ->assertJsonPath('data.grace_days', 5)
            ->assertJsonPath('data.trial_days', 14);

        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')
            ->assertJsonPath('data.payment.exchange_rate_syp', '14500.00')
            ->assertJsonPath('data.payment.syriatel_cash_number', '0933123456');

        $log = AuditLog::query()->sole();
        $this->assertSame('13000.00', $log->before['exchange_rate_syp']);
        $this->assertSame('14500.00', $log->after['exchange_rate_syp']);
    }

    public function test_values_are_validated_and_only_the_super_admin_may_change_them(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);

        $this->withToken($this->clerkToken('user_owner'))->patchJson('/api/v1/admin/settings/billing', ['trial_days' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.trial_days', ['min']);

        $this->withToken($this->clerkToken('user_admin'))->getJson('/api/v1/admin/settings/billing')->assertForbidden();
    }
}
