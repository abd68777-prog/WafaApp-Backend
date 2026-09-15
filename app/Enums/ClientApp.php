<?php

namespace App\Enums;

/**
 * The mobile app a push token belongs to — the customer and merchant apps are
 * separate Expo builds, so each has its own FCM token.
 */
enum ClientApp: string
{
    case Customer = 'customer';
    case Merchant = 'merchant';
}
