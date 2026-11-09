<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The store review account (config otp.review_phone). Apple and Google
 * reviewers sign in to it with a fixed code, so it is kept apart from
 * everything real: only the demo shops reach it, nothing is sent to it, and
 * signing in to it is never rate limited. The demo shops, in turn, are seen
 * by the review account only.
 */
final class ReviewAccount
{
    /**
     * The demo shops' Clerk ids start with this; no Clerk user has one.
     */
    public const DEMO_SHOP_PREFIX = 'review_demo_';

    /**
     * The review number in E.164, or null while the feature is off.
     */
    public static function phone(): ?string
    {
        return PhoneNumber::normalize((string) config('otp.review_phone'));
    }

    public static function is(?string $phoneE164): bool
    {
        return $phoneE164 !== null && $phoneE164 === self::phone();
    }

    /**
     * Leaves the demo shops out of a shop listing unless the review account
     * is the one looking.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $merchants
     * @return TBuilder
     */
    public static function hideDemoShopsFrom(Builder $merchants, Customer $customer): Builder
    {
        if (self::is($customer->phone)) {
            return $merchants;
        }

        return $merchants->where(fn (Builder $query) => $query
            ->whereNull('clerk_user_id')
            ->orWhere('clerk_user_id', 'not like', self::DEMO_SHOP_PREFIX.'%'));
    }

    public static function isDemoShop(Merchant $merchant): bool
    {
        return str_starts_with((string) $merchant->clerk_user_id, self::DEMO_SHOP_PREFIX);
    }
}
