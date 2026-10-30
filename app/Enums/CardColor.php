<?php

namespace App\Enums;

/**
 * The fixed palette a card is drawn in. Only the key is stored and sent;
 * each app maps it to its own color, and the hex here is for the merchant
 * app's picker and the dashboard. Every card has one; a key an app does
 * not know yet is drawn in navy, the default. Every color is dark enough for white text
 * (contrast at least 4.5:1), and there is no yellow or orange: amber means
 * "your reward is ready". A new color reaches the apps first, then is added
 * here.
 */
enum CardColor: string
{
    case Navy = 'navy';
    case Royal = 'royal';
    case Teal = 'teal';
    case Forest = 'forest';
    case Olive = 'olive';
    case Coffee = 'coffee';
    case Burgundy = 'burgundy';
    case Rose = 'rose';
    case Plum = 'plum';
    case Graphite = 'graphite';

    public function label(): string
    {
        return match ($this) {
            self::Navy => 'كحلي',
            self::Royal => 'أزرق ملكي',
            self::Teal => 'فيروزي',
            self::Forest => 'أخضر غامق',
            self::Olive => 'زيتوني',
            self::Coffee => 'بنّي',
            self::Burgundy => 'خمري',
            self::Rose => 'وردي غامق',
            self::Plum => 'بنفسجي',
            self::Graphite => 'رمادي فحمي',
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::Navy => '#1B4F72',
            self::Royal => '#2F4FB0',
            self::Teal => '#0E7C73',
            self::Forest => '#1F6F4A',
            self::Olive => '#4F5D2F',
            self::Coffee => '#6B4226',
            self::Burgundy => '#7A1F3D',
            self::Rose => '#A1325A',
            self::Plum => '#5B2A86',
            self::Graphite => '#3A3F47',
        };
    }
}
