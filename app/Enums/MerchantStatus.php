<?php

namespace App\Enums;

/**
 * Lifecycle of a merchant account (PRD 4.1, 5.2).
 *
 * Only an admin moves a merchant between these states — merchants can never
 * activate, upgrade or cancel themselves (PRD 4.2).
 */
enum MerchantStatus: string
{
    case PendingReview = 'pending_review';
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
}
