<?php

namespace App\Rules;

use App\Enums\ShopLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An `https://` link to one of the sites a ShopLink allows, with nothing
 * that could make it lead elsewhere: no credentials, no port, no spaces.
 */
class ShopLinkUrl implements ValidationRule
{
    public function __construct(private readonly ShopLink $link) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isAllowed($value)) {
            $fail("The :attribute must be an https link to {$this->link->name}.");
        }
    }

    private function isAllowed(string $url): bool
    {
        if (preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }

        return $this->link->accepts(strtolower($parts['host']), $parts['path'] ?? '');
    }
}
