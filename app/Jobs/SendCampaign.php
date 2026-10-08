<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Notifications\CampaignReceived;
use App\Services\Merchant\ShopCustomers;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Delivers a campaign to the shop's customers in batches, off the request,
 * so a shop with thousands of customers gets its answer at once. Who is
 * reached is decided again here: a customer who muted the shop in the
 * meantime hears nothing.
 */
class SendCampaign implements ShouldQueueAfterCommit
{
    use Queueable;

    private const BATCH = 500;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Campaign $campaign) {}

    public function handle(): void
    {
        $merchant = $this->campaign->merchant;

        if ($merchant === null) {
            return;
        }

        // One notification per customer: each inbox entry needs its own id.
        ShopCustomers::reachableByCampaigns($merchant)->chunkById(self::BATCH, function (Collection $customers) use ($merchant): void {
            foreach ($customers as $customer) {
                $customer->notify(new CampaignReceived($this->campaign, $merchant->business_name));
            }
        });
    }
}
