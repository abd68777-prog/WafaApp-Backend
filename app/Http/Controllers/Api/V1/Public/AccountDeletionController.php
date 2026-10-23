<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\DeletionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RequestOtpRequest;
use App\Http\Requests\Api\V1\Public\DeleteAccountRequest;
use App\Models\Customer;
use App\Services\Customer\CustomerAccountDeleter;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Deleting a customer account without installing the app, which Google Play
 * requires (privacy policy §10). The web page asks for the number, sends the
 * same WhatsApp code as sign-in, and deletes once the code is entered.
 *
 * Numbers a shop stamped before their owner signed up count too: whoever owns
 * one may have it removed.
 */
class AccountDeletionController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Answers the same way whether or not the number has an account, and only
     * spends a WhatsApp message when it does.
     */
    public function requestCode(RequestOtpRequest $request): JsonResponse
    {
        $phone = $request->phoneE164();

        if (Customer::query()->where('phone', $phone)->exists()) {
            return response()->json(['data' => $this->otp->issue($phone, $request->ip())], 202);
        }

        return response()->json(['data' => [
            'expires_in_seconds' => (int) config('otp.ttl'),
            'resend_after_seconds' => (int) config('otp.resend_after'),
        ]], 202);
    }

    public function destroy(DeleteAccountRequest $request, CustomerAccountDeleter $deleter): Response
    {
        $phone = $request->phoneE164();

        // Owning the number comes first; an unknown number never got a code.
        $otpCode = $this->otp->verify($phone, $request->string('code')->value());
        $this->otp->consume($otpCode);

        $customer = Customer::query()->where('phone', $phone)->first();

        if ($customer) {
            $deleter->delete($customer, DeletionSource::Web);
        }

        return response()->noContent();
    }
}
