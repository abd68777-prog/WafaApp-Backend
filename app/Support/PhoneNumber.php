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

    /**
     * The form merchants see: the local number with its middle hidden,
     * `0933***456` for `+963933123456` (privacy policy, contract §1.7).
     */
    public static function mask(string $e164): string
    {
        $local = '0'.substr($e164, strlen('+'.self::COUNTRY_CODE));

        return substr($local, 0, 4).'***'.substr($local, -3);
    }

    /**
     * A shop's public contact number: a mobile, normalized as above, or a
     * landline such as `011 222 3344` — an area code starting 1–5 and six
     * or seven digits — as `+963112223344`. Null when it is neither.
     */
    public static function normalizeContact(string $value): ?string
    {
        $mobile = self::normalize($value);

        if ($mobile !== null) {
            return $mobile;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        $digits = preg_replace('/^(00)?'.self::COUNTRY_CODE.'|^0/', '', $digits) ?? '';

        return preg_match('/^[1-5]\d{7,8}$/', $digits) === 1 ? '+'.self::COUNTRY_CODE.$digits : null;
    }

    public static function isValid(string $value): bool
    {
        return self::normalize($value) !== null;
    }
}
