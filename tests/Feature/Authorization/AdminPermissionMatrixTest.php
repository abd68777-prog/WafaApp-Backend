<?php

namespace Tests\Feature\Authorization;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The dashboard role matrix from requirements §5.1 (and the audit log screen
 * from §6.6), checked through the gates the routes use.
 */
class AdminPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rows of the requirements table: which roles hold each permission.
     *
     * @return array<string, array{AdminPermission, list<AdminRole>}>
     */
    public static function matrix(): array
    {
        $superAdmin = AdminRole::SuperAdmin;
        $admin = AdminRole::Admin;
        $reviewer = AdminRole::PaymentsReviewer;
        $support = AdminRole::Support;

        return [
            'create and delete dashboard accounts' => [AdminPermission::ManageAdminAccounts, [$superAdmin]],
            'edit packages and prices' => [AdminPermission::ManagePackages, [$superAdmin]],
            'exchange rate, payment details and settings' => [AdminPermission::ManageSettings, [$superAdmin]],
            'grant a manual extension' => [AdminPermission::GrantExtensions, [$superAdmin]],
            'read the audit log' => [AdminPermission::ViewAuditLog, [$superAdmin]],
            'approve or reject a payment proof' => [AdminPermission::ReviewPayments, [$reviewer]],
            'suspend or reactivate a merchant' => [AdminPermission::SuspendMerchants, [$superAdmin, $admin]],
            'execute deletion requests' => [AdminPermission::ExecuteDeletionRequests, [$superAdmin, $admin]],
            'cancel a stamp' => [AdminPermission::CancelStamps, [$superAdmin, $admin]],
            'edit a business name or type' => [AdminPermission::EditBusinessIdentity, [$superAdmin, $admin, $support]],
            'edit a customer birthdate' => [AdminPermission::EditCustomerBirthdate, [$superAdmin, $admin, $support]],
            'manage icons and business types' => [AdminPermission::ManageLookups, [$superAdmin, $admin]],
            'view merchants, cards and customers' => [AdminPermission::ViewMerchantsAndCustomers, [$superAdmin, $admin, $support]],
            'reveal a full customer phone number' => [AdminPermission::RevealCustomerPhone, [$superAdmin, $admin, $support]],
            'payment history and financial reports' => [AdminPermission::ViewFinancials, [$superAdmin, $reviewer]],
        ];
    }

    /**
     * @param  list<AdminRole>  $rolesAllowed
     */
    #[DataProvider('matrix')]
    public function test_each_role_holds_exactly_the_permissions_of_the_requirements_table(AdminPermission $permission, array $rolesAllowed): void
    {
        foreach (AdminRole::cases() as $role) {
            $admin = AdminUser::factory()->create(['role' => $role]);

            $this->assertSame(
                in_array($role, $rolesAllowed, true),
                Gate::forUser($admin)->allows($permission->value),
                "{$role->value} / {$permission->value}",
            );
        }
    }

    public function test_every_permission_is_in_the_matrix(): void
    {
        $covered = array_map(fn (array $row): AdminPermission => $row[0], self::matrix());

        $this->assertEqualsCanonicalizing(AdminPermission::cases(), array_values($covered));
    }

    public function test_a_merchant_passes_no_dashboard_gate(): void
    {
        $merchant = Merchant::factory()->create();

        foreach (AdminPermission::cases() as $permission) {
            $this->assertTrue(Gate::forUser($merchant)->denies($permission->value), $permission->value);
        }
    }
}
