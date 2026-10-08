<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Notifications\BirthdaysToday;
use App\Services\Merchant\Birthdays;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Tells each shop in the morning how many of its customers have their
 * birthday today (notification `birthdays_today`). Shops whose subscription
 * does not allow greetings are skipped, and a shop is told once a day even
 * if the command runs again.
 */
#[Signature('birthdays:notify-merchants')]
#[Description('Tell merchants how many of their customers have their birthday today')]
class NotifyMerchantBirthdays extends Command
{
    public function handle(): int
    {
        $today = Birthdays::today();
        $notified = 0;

        Merchant::query()
            ->whereNotNull('status')
            ->whereDoesntHave('notifications', fn (Builder $query) => $query
                ->where('type', 'birthdays_today')
                ->where('created_at', '>=', $today->utc()))
            ->chunkById(200, function ($merchants) use ($today, &$notified): void {
                foreach ($merchants as $merchant) {
                    if (! $merchant->status->canSendCampaigns()) {
                        continue;
                    }

                    $count = Birthdays::on($today, Birthdays::customersOf($merchant))->count();

                    if ($count > 0) {
                        $merchant->notify(new BirthdaysToday($count));
                        $notified++;
                    }
                }
            });

        $this->info("Notified {$notified} merchant(s).");

        return self::SUCCESS;
    }
}
