<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
