<?php

namespace App\Enums;

/**
 * Dashboard roles (requirements §5.1).
 *
 * The separation that matters: whoever approves a payment cannot change prices
 * or grant extensions, and not even a super admin approves payments — that way
 * no single account can activate a friend's shop for free and cover it up.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case PaymentsReviewer = 'payments_reviewer';
    case Support = 'support';

    /**
     * The permission matrix of §5.1, one role at a time.
     *
     * @return list<AdminPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                AdminPermission::ManageAdminAccounts,
                AdminPermission::ManagePackages,
                AdminPermission::ManageSettings,
                AdminPermission::GrantExtensions,
                AdminPermission::ViewAuditLog,
                AdminPermission::ViewFinancials,
                AdminPermission::SuspendMerchants,
                AdminPermission::ExecuteDeletionRequests,
                AdminPermission::CancelStamps,
                AdminPermission::ManageLookups,
                AdminPermission::EditBusinessIdentity,
                AdminPermission::EditCustomerBirthdate,
                AdminPermission::ViewMerchantsAndCustomers,
                AdminPermission::RevealCustomerPhone,
            ],
            self::Admin => [
                AdminPermission::SuspendMerchants,
                AdminPermission::ExecuteDeletionRequests,
                AdminPermission::CancelStamps,
                AdminPermission::ManageLookups,
                AdminPermission::EditBusinessIdentity,
                AdminPermission::EditCustomerBirthdate,
                AdminPermission::ViewMerchantsAndCustomers,
                AdminPermission::RevealCustomerPhone,
            ],
            self::PaymentsReviewer => [
                AdminPermission::ReviewPayments,
                AdminPermission::ViewFinancials,
            ],
            self::Support => [
                AdminPermission::EditBusinessIdentity,
                AdminPermission::EditCustomerBirthdate,
                AdminPermission::ViewMerchantsAndCustomers,
                AdminPermission::RevealCustomerPhone,
            ],
        };
    }
}
