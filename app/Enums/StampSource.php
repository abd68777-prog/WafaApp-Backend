<?php

namespace App\Enums;

/**
 * How the merchant identified the customer: scanning their personal QR code
 * or typing their phone number (PRD 4.6).
 */
enum StampSource: string
{
    case Qr = 'qr';
    case Phone = 'phone';
}
