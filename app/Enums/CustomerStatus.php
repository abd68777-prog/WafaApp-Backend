<?php

namespace App\Enums;

/**
 * A customer is `Pending` when a merchant enrolled them by phone number before
 * they installed the app (PRD 7.1 scenario C). Verifying that same number by
 * OTP turns the record `Active` and keeps all accumulated progress (scenario D).
 */
enum CustomerStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
