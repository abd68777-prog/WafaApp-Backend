<?php

namespace Tests\Unit;

use App\Support\LinkDetector;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Merchant texts to customers carry no links, with or without a scheme.
 */
class LinkDetectorTest extends TestCase
{
    #[TestWith(['زوروا https://example.com'])]
    #[TestWith(['http://bit.ly/x'])]
    #[TestWith(['www.wafa-offers'])]
    #[TestWith(['اطلبوا من shop.sy/offer'])]
    #[TestWith(['WAFA.COM'])]
    public function test_address_like_text_is_a_link(string $text): void
    {
        $this->assertTrue(LinkDetector::containsLink($text));
    }

    #[TestWith(['كل عام وأنت بخير!'])]
    #[TestWith(['خصم 2.5 بالمئة على القهوة'])]
    #[TestWith(['هديتك: قطعة كيك. نراك قريباً.'])]
    public function test_ordinary_text_is_not_a_link(string $text): void
    {
        $this->assertFalse(LinkDetector::containsLink($text));
    }
}
