<?php

namespace App\Support;

use App\Models\Merchant;

/**
 * The store review account (config otp.review_phone). Apple and Google
 * reviewers sign in to it with a fixed code, so it is kept apart from
 * everything real: only the demo shops reach it, nothing is sent to it, and
 * signing in to it is never rate limited.
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

    public static function isDemoShop(Merchant $merchant): bool
    {
        return str_starts_with((string) $merchant->clerk_user_id, self::DEMO_SHOP_PREFIX);
    }
}
