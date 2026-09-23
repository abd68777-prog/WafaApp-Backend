<?php

namespace App\Enums;

/**
 * What a dashboard account may do (requirements §5.1 and the screen list in
 * §6.6). Each case is registered as a gate named after its value, so routes
 * authorize with `->can(AdminPermission::ReviewPayments->value)`.
 *
 * Roles are sets of these permissions — see AdminRole::permissions().
 */
enum AdminPermission: string
{
    case ManageAdminAccounts = 'manage-admin-accounts';
    case ManagePackages = 'manage-packages';
    case ManageSettings = 'manage-settings';
    case GrantExtensions = 'grant-extensions';
    case ViewAuditLog = 'view-audit-log';
    case ReviewPayments = 'review-payments';
    case ViewFinancials = 'view-financials';
    case SuspendMerchants = 'suspend-merchants';
    case ExecuteDeletionRequests = 'execute-deletion-requests';
    case CancelStamps = 'cancel-stamps';
    case ManageLookups = 'manage-lookups';
    case EditBusinessIdentity = 'edit-business-identity';
    case EditCustomerBirthdate = 'edit-customer-birthdate';
    case ViewMerchantsAndCustomers = 'view-merchants-and-customers';
    case RevealCustomerPhone = 'reveal-customer-phone';
}
