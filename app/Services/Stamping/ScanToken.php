<?php

namespace App\Services\Stamping;

use App\Enums\ErrorCode;
use App\Enums\StampMethod;
use App\Exceptions\ApiException;
use App\Models\Merchant;
use Carbon\CarbonImmutable;
use ErrorException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use TypeError;
use ValueError;

/**
 * The token `scan/resolve` returns and the confirmation screen sends back to
 * add a stamp or hand over a reward (contract §5.3).
 *
 * It binds the shop, the customer and the card for three minutes, so the
 * cashier can confirm even after the customer's QR code rotated. Encrypted
 * with the application key like the PIN unlock token: the app cannot forge or
 * alter it, and nothing is stored on the server.
 */
final class ScanToken
{
    public const TTL_SECONDS = 180;

    public function issue(ScanTicket $ticket): string
    {
        return Crypt::encryptString(json_encode([
            'merchant' => $ticket->merchantId,
            'card' => $ticket->cardId,
            'customer' => $ticket->customerId,
            'phone' => $ticket->phone,
            'method' => $ticket->method->value,
            'expires' => $ticket->expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * A ticket for a scan that happened now.
     */
    public static function ticket(Merchant $merchant, int $cardId, ?int $customerId, ?string $phone, StampMethod $method): ScanTicket
    {
        return new ScanTicket(
            $merchant->id,
            $cardId,
            $customerId,
            $phone,
            $method,
            CarbonImmutable::now()->addSeconds(self::TTL_SECONDS),
        );
    }

    /**
     * @throws ApiException SCAN_TOKEN_EXPIRED when the token is old, forged,
     *                      or was issued to another shop.
     */
    public function read(Merchant $merchant, string $token): ScanTicket
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            $ticket = new ScanTicket(
                (int) $payload['merchant'],
                (int) $payload['card'],
                $payload['customer'] !== null ? (int) $payload['customer'] : null,
                $payload['phone'],
                StampMethod::from($payload['method']),
                CarbonImmutable::createFromTimestamp((int) $payload['expires']),
            );
        } catch (DecryptException|ErrorException|JsonException|TypeError|ValueError) {
            throw $this->expired();
        }

        if ($ticket->merchantId !== $merchant->id || $ticket->expiresAt->isPast()) {
            throw $this->expired();
        }

        return $ticket;
    }

    private function expired(): ApiException
    {
        return ApiException::of(ErrorCode::ScanTokenExpired, 'The scan is no longer valid; scan the customer again.');
    }
}
