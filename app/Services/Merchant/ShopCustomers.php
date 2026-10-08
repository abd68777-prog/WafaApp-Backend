<?php

namespace App\Services\Merchant;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Who counts as a shop's customer: a registered customer with a cycle on any
 * of its cards, even one whose reward was handed over long ago. A pending
 * customer — a phone number typed at the counter — has no app and receives
 * nothing.
 */
final class ShopCustomers
{
    /**
     * @return Builder<Customer>
     */
    public static function of(Merchant $merchant): Builder
    {
        return Customer::query()
            ->whereNotNull('registered_at')
            ->whereHas('cardCycles', fn (Builder $cycles) => $cycles->where('merchant_id', $merchant->id));
    }

    /**
     * Those a campaign reaches (requirements §7.3): everyone except customers
     * who switched off all offers or this shop's.
     *
     * @return Builder<Customer>
     */
    public static function reachableByCampaigns(Merchant $merchant): Builder
    {
        return self::of($merchant)
            ->where('campaigns_muted', false)
            ->whereDoesntHave('merchantMutes', fn (Builder $mutes) => $mutes->where('merchant_id', $merchant->id));
    }
}
