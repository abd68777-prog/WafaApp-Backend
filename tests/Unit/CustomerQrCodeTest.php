<?php

namespace Tests\Unit;

use App\Services\Customer\CustomerQrCode;
use App\Support\Base32;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * The customer app implements the same algorithm with a standard TOTP
 * library, so the server must match RFC 6238 exactly, not just itself.
 */
class CustomerQrCodeTest extends TestCase
{
    /**
     * The SHA-256 test vectors of RFC 6238, appendix B (30-second windows).
     */
    #[TestWith([59, '46119246'])]
    #[TestWith([1111111109, '68084774'])]
    #[TestWith([1111111111, '67062674'])]
    #[TestWith([1234567890, '91819424'])]
    #[TestWith([2000000000, '90698825'])]
    #[TestWith([20000000000, '77737706'])]
    public function test_codes_match_the_rfc_6238_sha256_vectors(int $timestamp, string $expected): void
    {
        $secret = Base32::encode('12345678901234567890123456789012');

        $this->assertSame($expected, CustomerQrCode::codeAt($secret, $timestamp, 30));
    }

    public function test_base32_round_trips_a_secret_in_the_rfc_4648_alphabet(): void
    {
        $bytes = random_bytes(20);
        $encoded = Base32::encode($bytes);

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $encoded);
        $this->assertSame($bytes, Base32::decode($encoded));
        $this->assertSame('MZXW6YTBOI', Base32::encode('foobar'));
    }
}
