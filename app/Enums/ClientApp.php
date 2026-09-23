<?php

namespace App\Enums;

/**
 * The customer and merchant apps are separate builds, so each device token
 * belongs to one of them.
 */
enum ClientApp: string
{
    case Customer = 'customer';
    case Merchant = 'merchant';
}
