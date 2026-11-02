<?php

namespace App\Enums;

/**
 * Where a merchant sends the subscription money, outside the app; the
 * merchant uploads the proof afterwards. Each method's details (account,
 * recipient, QR) are set from the dashboard as a PaymentAccount.
 */
enum PaymentMethod: string
{
    case ShamCash = 'sham_cash';
    case Haram = 'haram';

    public function label(): string
    {
        return match ($this) {
            self::ShamCash => 'شام كاش',
            self::Haram => 'الهرم',
        };
    }
}
