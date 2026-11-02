<?php

namespace App\Enums;

/**
 * The links a shop may show on its page, each limited to its own sites:
 * what a merchant writes reaches every customer, so store review wants it
 * filtered, and an open address would be a door for scam links. A website
 * of the shop's own is left for later for the same reason.
 */
enum ShopLink: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Maps = 'maps';

    public function column(): string
    {
        return $this->value.'_url';
    }

    /**
     * Whether a link to this host and path belongs to this kind. Hosts are
     * matched whole, so `instagram.com.example.sy` is not Instagram.
     */
    public function accepts(string $host, string $path): bool
    {
        return match ($this) {
            self::Instagram => in_array($host, ['instagram.com', 'www.instagram.com'], true),
            self::Facebook => in_array($host, ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com', 'fb.me'], true),
            self::Maps => in_array($host, ['maps.app.goo.gl', 'maps.google.com'], true)
                || (in_array($host, ['goo.gl', 'google.com', 'www.google.com'], true) && str_starts_with($path, '/maps')),
        };
    }
}
