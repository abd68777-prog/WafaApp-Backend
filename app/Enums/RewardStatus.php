<?php

namespace App\Enums;

/**
 * A reward is `Ready` the moment it is earned and becomes `Redeemed` only when
 * the merchant confirms the customer collected it (PRD 3.3).
 */
enum RewardStatus: string
{
    case Ready = 'ready';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
