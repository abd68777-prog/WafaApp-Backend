<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Dashboard accounts are managed by super admins only (requirements §5.1).
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private const SUPER_ADMIN = 'user_super_admin';

    /**
     * @return array<string, array{AdminRole}>
     */
    public static function rolesWithoutAccountManagement(): array
    {
        return [
            'admin' => [AdminRole::Admin],
            'payments reviewer' => [AdminRole::PaymentsReviewer],
            'support' => [AdminRole::Support],
        ];
    }

    #[DataProvider('rolesWithoutAccountManagement')]
    public function test_only_a_super_admin_may_manage_dashboard_accounts(AdminRole $role): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_staff', 'role' => $role]);

        $response = $this->withToken($this->clerkToken('user_staff'))->postJson('/api/v1/admin/admin-users', [
            'name' => 'New Reviewer',
            'email' => 'new@wafa.test',
            'role' => 'payments_reviewer',
        ]);

        $response->assertForbidden()->assertExactJson([
            'message' => 'Your role does not allow this action.',
            'code' => 'permission_denied',
        ]);

        $this->assertDatabaseMissing('admin_users', ['email' => 'new@wafa.test']);
    }

    public function test_a_super_admin_lists_every_account(): void
    {
        $this->superAdmin();
        AdminUser::factory()->support()->unlinked()->create(['name' => 'Support Agent']);

        $response = $this->withToken($this->superAdminToken())->getJson('/api/v1/admin/admin-users');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Support Agent', 'linked' => false]);
    }

    public function test_a_super_admin_adds_an_account_waiting_for_its_first_sign_in(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->withToken($this->superAdminToken())->postJson('/api/v1/admin/admin-users', [
            'name' => 'Payments Reviewer',
            'email' => 'Reviewer@Wafa.test',
            'role' => 'payments_reviewer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'reviewer@wafa.test')
            ->assertJsonPath('data.role', 'payments_reviewer')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.linked', false);

        $added = AdminUser::query()->where('email', 'reviewer@wafa.test')->sole();
        $this->assertNull($added->clerk_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $superAdmin->id,
            'action' => 'admin_user.created',
            'subject_type' => 'admin',
            'subject_id' => $added->id,
        ]);
    }

    public function test_adding_an_account_with_an_email_already_used_is_rejected_whatever_its_case(): void
    {
        $this->superAdmin();
        AdminUser::factory()->create(['email' => 'staff@wafa.test']);

        $response = $this->withToken($this->superAdminToken())->postJson('/api/v1/admin/admin-users', [
            'name' => 'Duplicate',
            'email' => 'STAFF@wafa.test',
            'role' => 'support',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('admin_users', 2);
    }

    public function test_changing_a_role_records_the_value_before_and_after(): void
    {
        $superAdmin = $this->superAdmin();
        $staff = AdminUser::factory()->support()->create();

        $response = $this->withToken($this->superAdminToken())
            ->patchJson("/api/v1/admin/admin-users/{$staff->id}", ['role' => 'admin']);

        $response->assertOk()->assertJsonPath('data.role', 'admin');

        $this->assertSame(AdminRole::Admin, $staff->fresh()->role);
        $log = $superAdmin->auditLogs()->where('action', 'admin_user.updated')->sole();
        $this->assertSame(['role' => 'support'], $log->before);
        $this->assertSame(['role' => 'admin'], $log->after);
    }

    public function test_the_email_of_a_linked_account_cannot_change(): void
    {
        $this->superAdmin();
        $staff = AdminUser::factory()->create(['email' => 'staff@wafa.test']);

        $response = $this->withToken($this->superAdminToken())
            ->patchJson("/api/v1/admin/admin-users/{$staff->id}", ['email' => 'other@wafa.test']);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame('staff@wafa.test', $staff->fresh()->email);
    }

    public function test_the_email_of_an_account_not_linked_yet_can_be_corrected(): void
    {
        $this->superAdmin();
        $staff = AdminUser::factory()->unlinked()->create(['email' => 'typo@wafa.tset']);

        $response = $this->withToken($this->superAdminToken())
            ->patchJson("/api/v1/admin/admin-users/{$staff->id}", ['email' => 'staff@wafa.test']);

        $response->assertOk()->assertJsonPath('data.email', 'staff@wafa.test');
    }

    public function test_deleting_an_account_deactivates_it_and_ends_its_access(): void
    {
        $superAdmin = $this->superAdmin();
        $staff = AdminUser::factory()->create(['clerk_user_id' => 'user_staff']);

        $response = $this->withToken($this->superAdminToken())->deleteJson("/api/v1/admin/admin-users/{$staff->id}");

        $response->assertOk()->assertJsonPath('data.is_active', false);

        // The row stays so the audit trail keeps pointing at who did what.
        $this->assertModelExists($staff);
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $superAdmin->id,
            'action' => 'admin_user.deactivated',
            'subject_id' => $staff->id,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->clerkToken('user_staff'))->getJson('/api/v1/admin/auth/me')->assertForbidden();
    }

    public function test_a_super_admin_cannot_demote_themselves(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->withToken($this->superAdminToken())
            ->patchJson("/api/v1/admin/admin-users/{$superAdmin->id}", ['role' => 'admin']);

        $response->assertUnprocessable()->assertJsonValidationErrors('admin_user');

        $this->assertSame(AdminRole::SuperAdmin, $superAdmin->fresh()->role);
    }

    public function test_a_super_admin_cannot_deactivate_themselves(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->withToken($this->superAdminToken())->deleteJson("/api/v1/admin/admin-users/{$superAdmin->id}");

        $response->assertUnprocessable()->assertJsonValidationErrors('admin_user');

        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_a_super_admin_may_rename_themselves(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->withToken($this->superAdminToken())
            ->patchJson("/api/v1/admin/admin-users/{$superAdmin->id}", ['name' => 'Platform Owner']);

        $response->assertOk()->assertJsonPath('data.name', 'Platform Owner');
    }

    private function superAdmin(): AdminUser
    {
        return AdminUser::factory()->superAdmin()->create(['clerk_user_id' => self::SUPER_ADMIN]);
    }

    private function superAdminToken(): string
    {
        return $this->clerkToken(self::SUPER_ADMIN);
    }
}
