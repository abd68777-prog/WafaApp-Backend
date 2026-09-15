<?php

namespace App\Enums;

/**
 * Who receives a merchant's manual promotion (PRD 4.8): every customer of the
 * merchant, the customers of one card, or a hand-picked group.
 */
enum CampaignAudience: string
{
    case All = 'all';
    case Card = 'card';
    case Selected = 'selected';
}
