<?php

namespace App\Support;

/**
 * Finds links in text merchants send to customers. Campaigns and birthday
 * greetings are text only: a link is a door for scam links (requirements
 * §7.3), so any address-like text is refused, with or without a scheme.
 */
final class LinkDetector
{
    private const PATTERNS = [
        // https://…, http://…
        '~\bhttps?://~iu',
        // www.example…
        '~\bwww\.~iu',
        // example.com, shop.sy/offer — a Latin name, a dot and a Latin top-level domain
        '~\b[a-z0-9][a-z0-9-]*\.[a-z]{2,24}\b~iu',
    ];

    public static function containsLink(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
