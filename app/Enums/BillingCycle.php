<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Trial = 'trial';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
