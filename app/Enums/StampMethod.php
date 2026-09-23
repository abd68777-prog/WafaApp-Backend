<?php

namespace App\Enums;

/**
 * How the customer was identified for this stamp. Merchants see how many of
 * their stamps were added by phone number rather than by scanning.
 */
enum StampMethod: string
{
    case Qr = 'qr';
    case Phone = 'phone';
}
