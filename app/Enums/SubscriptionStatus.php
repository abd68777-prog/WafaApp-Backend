<?php

namespace App\Enums;

/**
 * `Superseded` marks a period that an admin replaced with a package change,
 * so the subscriptions table doubles as the audit trail for PRD 4.2.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
