<?php

namespace App\Support;

/**
 * Normalizes Syrian mobile numbers to E.164.
 *
 * The phone number is the customer's identity, so the same person must never
 * end up with two rows because they typed `0947…` once and `+963947…` the next
 * time. Every write path runs the input through here first.
 */
final class PhoneNumber
{
    private const COUNTRY_CODE = '963';

    /**
     * Turn any accepted local or international spelling into `+9639XXXXXXXX`.
     *
     * Accepts `09XXXXXXXX`, `9XXXXXXXX`, `9639XXXXXXXX`, `009639XXXXXXXX` and
     * `+9639XXXXXXXX`, with or without spaces and dashes. Returns null when the
     * value is not a valid Syrian mobile number.
     */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, self::COUNTRY_CODE)) {
            $digits = substr($digits, strlen(self::COUNTRY_CODE));
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Syrian mobile numbers are nine digits and always start with 9.
        if (preg_match('/^9\d{8}$/', $digits) !== 1) {
            return null;
        }

        return '+'.self::COUNTRY_CODE.$digits;
    }

    public static function isValid(string $value): bool
    {
        return self::normalize($value) !== null;
    }
}
