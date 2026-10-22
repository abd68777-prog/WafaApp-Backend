<?php

namespace App\Support;

/**
 * The icon names the apps can draw: both apps render card icons with Phosphor
 * Icons, so a key outside this list shows up blank or breaks.
 *
 * The list lives in resources/data/phosphor-icons.json, taken from the file
 * names under assets/regular in the @phosphor-icons/core package. To follow a
 * newer Phosphor release, regenerate it from that package's version.
 */
final class PhosphorIcons
{
    private const FILE = 'data/phosphor-icons.json';

    /**
     * @var list<string>|null
     */
    private static ?array $names = null;

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return self::$names ??= json_decode(
            (string) file_get_contents(resource_path(self::FILE)),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['names'];
    }

    public static function exists(string $name): bool
    {
        return in_array($name, self::names(), true);
    }
}
