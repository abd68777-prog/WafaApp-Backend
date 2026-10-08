<?php

namespace App\Services\Merchant;

use App\Models\Customer;
use App\Models\Merchant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Whose birthday it is, for the merchant's list, the greeting and the
 * morning reminder. Days are counted in Damascus.
 */
class Birthdays
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(MerchantState::TIMEZONE)->startOfDay();
    }

    /**
     * The shop's customers who gave a date of birth.
     *
     * @return Builder<Customer>
     */
    public static function customersOf(Merchant $merchant): Builder
    {
        return ShopCustomers::of($merchant)->whereNotNull('birthdate');
    }

    /**
     * Customers born on this day and month. In a year without 29 February,
     * those born on it celebrate on the 28th.
     *
     * @param  Builder<Customer>  $customers
     * @return Builder<Customer>
     */
    public static function on(CarbonImmutable $day, Builder $customers): Builder
    {
        $leapDayMovesHere = $day->month === 2 && $day->day === 28 && ! $day->isLeapYear();

        return $customers
            ->whereMonth('birthdate', $day->month)
            ->where(fn (Builder $query) => $query
                ->whereDay('birthdate', $day->day)
                ->when($leapDayMovesHere, fn (Builder $query) => $query->orWhereDay('birthdate', 29)));
    }
}
