<?php

namespace App\Enums;

enum SubscriptionPeriodType: string
{
    case Trial = 'trial';
    case Paid = 'paid';
}
