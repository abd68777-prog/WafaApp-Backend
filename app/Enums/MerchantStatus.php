<?php

namespace App\Enums;

/**
 * Subscription lifecycle of a merchant account (requirements §3.1).
 *
 * Dates and admin decisions determine the status; payment review has its own
 * cycle and never puts the subscription into a special state.
 */
enum MerchantStatus: string
{
    case Trial = 'TRIAL';
    case Active = 'ACTIVE';
    case Grace = 'GRACE';
    case Expired = 'EXPIRED';
    case Suspended = 'SUSPENDED';
    case PendingDeletion = 'PENDING_DELETION';
    case Deleted = 'DELETED';

    /**
     * Statuses where the merchant may still add stamps and take new customers.
     */
    public function canCollectStamps(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace], true);
    }

    /**
     * Handing over an earned reward stays possible in every non-final status:
     * the reward belongs to the customer, not to the merchant's billing state.
     */
    public function canRedeemRewards(): bool
    {
        return $this !== self::Deleted;
    }

    /**
     * Only merchants who can actually stamp appear in the customer directory.
     */
    public function appearsInDirectory(): bool
    {
        return $this->canCollectStamps();
    }
}
