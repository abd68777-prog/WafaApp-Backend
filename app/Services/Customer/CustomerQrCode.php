<?php

namespace App\Services\Customer;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Setting;
use App\Support\Base32;
use Illuminate\Support\Str;

/**
 * The customer's rotating QR code (API contract, decision 3 and QrSecret).
 *
 * The app generates it offline with TOTP (RFC 6238): HMAC-SHA256, 8 digits,
 * a window of `qr_period_seconds`, keyed with the Base32 secret. The code reads
 * `W1.{qr_id}.{code}`. `qr_id` is random, so the code carries no phone number,
 * no name and nothing that reveals how many customers there are.
 *
 * A screenshot is useless a minute later: the server accepts the current and
 * the previous window only.
 */
final class CustomerQrCode
{
    public const PREFIX = 'W1';

    public const ALGORITHM = 'SHA256';

    public const DIGITS = 8;

    public const QR_ID_LENGTH = 12;

    /**
     * How far back an old code is still recognised as "expired" rather than
     * "invalid", so the cashier can tell the customer to reopen their code.
     */
    private const EXPIRED_LOOKBACK_WINDOWS = 30;

    /**
     * Give a customer the QR identity they are missing. Both values are kept
     * for the life of the account and emptied when it is deleted.
     */
    public function assignTo(Customer $customer): void
    {
        $customer->forceFill([
            'qr_id' => $customer->qr_id ?? $this->newQrId(),
            'qr_secret' => $customer->qr_secret ?? self::newSecret(),
        ]);
    }

    /**
     * What `GET /customer/me/qr` hands the app to generate codes with.
     *
     * @return array{qr_id: string, secret: string, algorithm: string, digits: int, period_seconds: int}
     */
    public function secretFor(Customer $customer): array
    {
        return [
            'qr_id' => $customer->qr_id,
            'secret' => $customer->qr_secret,
            'algorithm' => self::ALGORITHM,
            'digits' => self::DIGITS,
            'period_seconds' => $this->period(),
        ];
    }

    /**
     * The registered customer a scanned code belongs to.
     *
     * @throws ApiException QR_INVALID when the code is unreadable or matches no
     *                      customer, QR_EXPIRED when it was valid a while ago.
     */
    public function resolve(string $scanned, ?int $timestamp = null): Customer
    {
        $pattern = '/^'.self::PREFIX.'\.([A-Za-z0-9]{'.self::QR_ID_LENGTH.'})\.(\d{'.self::DIGITS.'})$/';

        if (preg_match($pattern, trim($scanned), $parts) !== 1) {
            throw $this->invalid();
        }

        $customer = Customer::query()
            ->where('qr_id', $parts[1])
            ->whereNotNull('registered_at')
            ->whereNotNull('qr_secret')
            ->first();

        if ($customer === null) {
            throw $this->invalid();
        }

        $timestamp ??= now()->getTimestamp();
        $period = $this->period();

        foreach ([0, 1] as $windowsAgo) {
            if (hash_equals(self::codeAt($customer->qr_secret, $timestamp - $windowsAgo * $period, $period), $parts[2])) {
                return $customer;
            }
        }

        for ($windowsAgo = 2; $windowsAgo <= self::EXPIRED_LOOKBACK_WINDOWS; $windowsAgo++) {
            if (hash_equals(self::codeAt($customer->qr_secret, $timestamp - $windowsAgo * $period, $period), $parts[2])) {
                throw ApiException::of(ErrorCode::QrExpired, 'This code is too old; ask the customer to reopen it.');
            }
        }

        throw $this->invalid();
    }

    /**
     * The TOTP code for the window containing `$timestamp` (RFC 6238 with
     * HMAC-SHA256 and dynamic truncation from RFC 4226).
     */
    public static function codeAt(string $base32Secret, int $timestamp, int $period): string
    {
        $counter = intdiv($timestamp, $period);
        $hash = hash_hmac('sha256', pack('J', $counter), Base32::decode($base32Secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 160 random bits, the key size RFC 4226 recommends.
     */
    public static function newSecret(): string
    {
        return Base32::encode(random_bytes(20));
    }

    public function period(): int
    {
        return max(1, (int) Setting::read('qr_period_seconds', 60));
    }

    private function newQrId(): string
    {
        do {
            $qrId = Str::random(self::QR_ID_LENGTH);
        } while (Customer::withTrashed()->where('qr_id', $qrId)->exists());

        return $qrId;
    }

    private function invalid(): ApiException
    {
        return ApiException::of(ErrorCode::QrInvalid, 'This is not a valid customer code.');
    }
}
