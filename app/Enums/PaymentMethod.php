<?php

namespace App\Enums;

/**
 * Manual transfer channels available in Syria (PRD 4.1).
 */
enum PaymentMethod: string
{
    case SyriatelCash = 'syriatel_cash';
    case Transfer = 'transfer';
    case Other = 'other';
}
