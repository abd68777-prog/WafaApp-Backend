<?php

namespace App\Enums;

/**
 * Transfers happen outside the app; the merchant uploads the proof afterwards.
 */
enum PaymentMethod: string
{
    case SyriatelCash = 'syriatel_cash';
    case Transfer = 'transfer';
}
