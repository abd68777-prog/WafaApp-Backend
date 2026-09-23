<?php

namespace App\Enums;

/**
 * A published card is never edited, only suspended. A suspended card takes no
 * new customers but existing ones can still finish it.
 */
enum CardStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
