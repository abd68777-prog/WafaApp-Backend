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

    /**
     * How the merchant's notification words the reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::TransferNotReceived => 'الحوالة لم تصل',
            self::AmountShort => 'المبلغ ناقص',
            self::UnclearImage => 'الصورة غير واضحة',
            self::InvalidProof => 'الإثبات غير صحيح',
        };
    }
}
