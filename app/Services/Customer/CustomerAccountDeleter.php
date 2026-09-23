<?php

namespace App\Services\Customer;

use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\DeletionRequest;
use App\Models\OtpCode;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a customer account the way the privacy policy (§10) describes.
 *
 * Personal data goes immediately: phone, name, birthdate, the QR secret, push
 * tokens, sign-in tokens, consents and preferences. The row itself stays,
 * empty, so the stamps and visits it holds keep counting in merchant
 * statistics without pointing at anyone. Uncollected stamps and rewards are
 * lost for good: nothing can present that QR code or phone number again.
 *
 * Used by the app today, and by the dashboard for requests arriving from the
 * web page or by email.
 */
final class CustomerAccountDeleter
{
    public function delete(Customer $customer, DeletionSource $source, ?AdminUser $handledBy = null): void
    {
        DB::transaction(function () use ($customer, $source, $handledBy): void {
            if ($customer->phone !== null) {
                OtpCode::query()->where('phone', $customer->phone)->delete();
            }

            $customer->tokens()->delete();
            $customer->deviceTokens()->delete();
            $customer->notifications()->delete();
            $customer->policyConsents()->delete();
            $customer->merchantMutes()->delete();
            $customer->birthdayGreetings()->delete();

            $customer->forceFill([
                'phone' => null,
                'name' => null,
                'birthdate' => null,
                'qr_secret' => null,
            ])->save();

            $customer->delete();

            $deletionRequest = new DeletionRequest([
                'subject_type' => DeletionSubjectType::Customer,
                'subject_id' => $customer->id,
                'source' => $source,
                'requested_at' => now(),
            ]);

            $deletionRequest->forceFill([
                'executed_at' => now(),
                'handled_by_admin_id' => $handledBy?->id,
            ])->save();
        });
    }
}
