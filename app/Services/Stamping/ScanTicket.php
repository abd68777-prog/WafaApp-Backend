<?php

namespace App\Services\Stamping;

use App\Enums\StampMethod;
use Carbon\CarbonImmutable;

/**
 * What a scan token vouches for: this shop scanned this customer (or typed
 * this number) for this card, a moment ago.
 *
 * A number nobody registered yet has no customer row: the pending customer is
 * created when the stamp is confirmed, not on the preview (decision 4), so
 * the ticket carries the number instead.
 */
final readonly class ScanTicket
{
    public function __construct(
        public int $merchantId,
        public int $cardId,
        public ?int $customerId,
        public ?string $phone,
        public StampMethod $method,
        public CarbonImmutable $expiresAt,
    ) {}
}
