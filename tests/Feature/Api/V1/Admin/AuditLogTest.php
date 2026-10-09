<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Merchant;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit trail (requirements §5.1): newest first, filtered by action, by
 * who acted or by what they acted on — for the Super Admin only.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_reads_and_filters_the_trail(): void
    {
        $owner = AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
        $support = AdminUser::factory()->support()->create();
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->create();
        $audit = app(AuditLogger::class);

        $audit->record($support, 'customer.phone_revealed', $customer, ipAddress: '10.0.0.1');
        $audit->record($owner, 'merchant.suspended', $merchant, ['status' => 'ACTIVE'], ['status' => 'SUSPENDED']);
        $audit->record(null, 'merchant.clerk_user_deleted', $merchant);

        $this->asOwner()->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action', 'merchant.clerk_user_deleted')
            ->assertJsonPath('data.0.admin', null)
            ->assertJsonPath('data.1.admin.id', $owner->id)
            ->assertJsonPath('data.1.after.status', 'SUSPENDED')
            ->assertJsonPath('data.2.ip_address', '10.0.0.1');

        $this->asOwner()->getJson('/api/v1/admin/audit-logs?action=customer.phone_revealed')->assertJsonCount(1, 'data');
        $this->asOwner()->getJson("/api/v1/admin/audit-logs?admin_user_id={$support->id}")->assertJsonCount(1, 'data');
        $this->asOwner()->getJson("/api/v1/admin/audit-logs?subject_type=merchant&subject_id={$merchant->id}")->assertJsonCount(2, 'data');
        $this->asOwner()->getJson("/api/v1/admin/audit-logs?subject_id={$merchant->id}")
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['subject_type' => ['required']]);
    }

    public function test_only_the_super_admin_reads_the_trail(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);

        $this->withToken($this->clerkToken('user_admin'))->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    }

    private function asOwner(): self
    {
        return $this->withToken($this->clerkToken('user_owner'));
    }
}
