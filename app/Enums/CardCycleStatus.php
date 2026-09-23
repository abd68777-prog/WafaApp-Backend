<?php

namespace App\Enums;

/**
 * The customer's copy of a card, for one cycle (requirements §2.1).
 *
 * A completed cycle is locked until the reward is handed over; the counter
 * never resets in place — redemption opens a new empty cycle instead.
 */
enum CardCycleStatus: string
{
    case Collecting = 'COLLECTING';
    case RewardReady = 'REWARD_READY';
    case Redeemed = 'REDEEMED';
}
