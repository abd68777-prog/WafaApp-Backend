<?php

namespace App\Enums;

/**
 * A closed list (requirements §3.3): the reviewer picks a reason, never types
 * one, so the merchant always gets an actionable message.
 */
enum PaymentRejectionReason: string
{
    case TransferNotReceived = 'transfer_not_received';
    case AmountShort = 'amount_short';
    case UnclearImage = 'unclear_image';
    case InvalidProof = 'invalid_proof';
}
