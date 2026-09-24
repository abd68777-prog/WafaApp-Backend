<?php

namespace App\Services\Otp;

use App\Exceptions\OtpDeliveryException;
use App\Models\OtpCode;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues and checks phone verification codes.
 *
 * Works on a bare phone number so any phone-verification flow can reuse it.
 * Only the hash of a code is ever stored.
 */
final class OtpService
{
    public function __construct(private readonly OtpSender $sender) {}

    /**
     * Generate a code, store its hash and hand it to the sender.
     *
     * The whole issue is one transaction: if delivery fails, the row is rolled
     * back so the customer is not locked out by a cooldown for a code that
     * never arrived.
     *
     * @return array{expires_in: int, resend_after: int}
     *
     * @throws OtpDeliveryException
     */
    public function issue(string $phoneE164, ?string $ipAddress = null): array
    {
        $this->guardAgainstResend($phoneE164);

        $ttl = (int) config('otp.ttl');
        $reviewCode = $this->reviewCodeFor($phoneE164);
        $code = $reviewCode ?? $this->generateCode();

        DB::transaction(function () use ($phoneE164, $code, $ipAddress, $ttl, $reviewCode): void {
            // A newly requested code replaces any code still outstanding.
            OtpCode::query()
                ->where('phone', $phoneE164)
                ->whereNull('consumed_at')
                ->delete();

            OtpCode::query()->create([
                'phone' => $phoneE164,
                'code_hash' => Hash::make($code),
                'channel' => 'whatsapp',
                'expires_at' => now()->addSeconds($ttl),
                'ip_address' => $ipAddress,
            ]);

            // The store reviewers already know their code from the review
            // notes, so nothing is sent and no credit is spent.
            if ($reviewCode === null) {
                $this->sender->send($phoneE164, $code, (string) Str::uuid());
            }
        });

        return [
            'expires_in' => $ttl,
            'resend_after' => (int) config('otp.resend_after'),
        ];
    }

    /**
     * Check a code without consuming it.
     *
     * Returning the row instead of consuming it lets the caller run its own
     * checks (such as asking a new customer for their name) and fail without
     * burning a code the customer already received.
     *
     * @throws ValidationException
     */
    public function verify(string $phoneE164, string $code): OtpCode
    {
        $otp = OtpCode::query()
            ->where('phone', $phoneE164)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->expires_at->isPast() || $otp->attempts >= (int) config('otp.max_attempts')) {
            $this->failVerification();
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            $this->failVerification();
        }

        return $otp;
    }

    public function consume(OtpCode $otp): void
    {
        $otp->forceFill(['consumed_at' => now()])->save();
    }

    /**
     * @throws OtpDeliveryException
     */
    private function guardAgainstResend(string $phoneE164): void
    {
        $lastCode = OtpCode::query()
            ->where('phone', $phoneE164)
            ->latest('id')
            ->first();

        if (! $lastCode) {
            return;
        }

        $availableAt = $lastCode->created_at->addSeconds((int) config('otp.resend_after'));

        if ($availableAt->isFuture()) {
            throw OtpDeliveryException::cooldown(now()->diffInSeconds($availableAt, absolute: true) ?: 1);
        }
    }

    /**
     * The fixed code of the store review account, for that exact number only.
     * A code that does not fit the normal code format switches it off rather
     * than weakening verification.
     */
    private function reviewCodeFor(string $phoneE164): ?string
    {
        $reviewPhone = PhoneNumber::normalize((string) config('otp.review_phone'));
        $reviewCode = (string) config('otp.review_code');

        if ($reviewPhone === null || $reviewPhone !== $phoneE164) {
            return null;
        }

        if (preg_match('/^\d{'.(int) config('otp.length').'}$/', $reviewCode) !== 1) {
            return null;
        }

        return $reviewCode;
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * One message for every failure mode, so the endpoint cannot be used to
     * tell an expired code from a wrong one.
     *
     * @throws ValidationException
     */
    private function failVerification(): never
    {
        throw ValidationException::withMessages([
            'code' => ['The verification code is invalid or has expired.'],
        ]);
    }
}
