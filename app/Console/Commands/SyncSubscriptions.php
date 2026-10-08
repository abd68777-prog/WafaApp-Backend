<?php

namespace App\Console\Commands;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Services\Billing\SubscriptionLifecycle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Applies the passing of time to every shop's subscription: trials and paid
 * periods run out, grace days start and end, reminders go out, and a smaller
 * package that has just started suspends the cards beyond its limit.
 */
#[Signature('subscriptions:sync')]
#[Description('Update subscription statuses from their dates and send the reminders')]
class SyncSubscriptions extends Command
{
    public function handle(SubscriptionLifecycle $lifecycle): int
    {
        Merchant::query()
            ->whereIn('status', [MerchantStatus::Trial, MerchantStatus::Active, MerchantStatus::Grace, MerchantStatus::Expired])
            ->chunkById(200, function ($merchants) use ($lifecycle): void {
                foreach ($merchants as $merchant) {
                    $lifecycle->sync($merchant);
                }
            });

        return self::SUCCESS;
    }
}
