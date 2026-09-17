<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RequestOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyOtpRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer authentication: phone number plus a one-time code over WhatsApp.
 *
 * Merchants and admins authenticate through Clerk instead, so this is the only
 * flow that issues a `customer` token.
 */
class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Send a verification code to the phone number.
     *
     * The response is identical for a known and an unknown number, so the
     * endpoint cannot be used to discover who is registered.
     */
    public function requestCode(RequestOtpRequest $request): JsonResponse
    {
        $result = $this->otp->issue($request->phoneE164(), $request->ip());

        return response()->json([
            'message' => 'Verification code sent.',
            'expires_in' => $result['expires_in'],
            'resend_after' => $result['resend_after'],
        ]);
    }

    /**
     * Exchange a valid code for an API token, creating the account if needed.
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phoneE164();

        // Checked before anything else, so owning the number is what unlocks
        // any information about the account behind it.
        $otpCode = $this->otp->verify($phone, $request->string('code')->value());

        $customer = Customer::query()->where('phone', $phone)->first();

        if ($customer?->status === CustomerStatus::Suspended) {
            abort(403, 'This account has been suspended.');
        }

        $isNewCustomer = $customer === null || $customer->status === CustomerStatus::Pending;

        if ($isNewCustomer && blank($request->input('name'))) {
            // The code stays usable so the customer can resubmit with a name
            // instead of waiting for a new message.
            throw ValidationException::withMessages([
                'name' => ['Your name is required to finish creating your account.'],
            ]);
        }

        $customer = DB::transaction(function () use ($customer, $phone, $request, $otpCode): Customer {
            $this->otp->consume($otpCode);

            return $this->activate($customer, $phone, $request);
        });

        return response()->json([
            'data' => new CustomerResource($customer),
            'token' => $customer->createToken(
                $request->string('device_name', 'customer-app')->value(),
                ['customer'],
            )->plainTextToken,
            'token_type' => 'Bearer',
            'is_new_customer' => $isNewCustomer,
        ]);
    }

    public function me(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }

    /**
     * Revoke only the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Revoke every token of the customer (sign out on all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out on all devices.']);
    }

    /**
     * Create the customer, or turn a merchant-created `pending` row into a real
     * account. Either way it is the same row, so all stamps collected before
     * the customer installed the app stay attached (PRD 7.1 scenario D).
     */
    private function activate(?Customer $customer, string $phone, VerifyOtpRequest $request): Customer
    {
        $customer ??= new Customer(['phone' => $phone]);

        if ($customer->status !== CustomerStatus::Active) {
            $customer->fill($request->safe()->only('name', 'birthdate'));
        }

        $customer->forceFill([
            'status' => CustomerStatus::Active,
            'qr_token' => $customer->qr_token ?? $this->generateQrToken(),
            'phone_verified_at' => $customer->phone_verified_at ?? now(),
            'registered_at' => $customer->registered_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        return $customer;
    }

    /**
     * An opaque permanent identity, not derived from the customer id so one
     * customer's code cannot be guessed from another's.
     */
    private function generateQrToken(): string
    {
        do {
            $token = Str::random(40);
        } while (Customer::query()->where('qr_token', $token)->exists());

        return $token;
    }
}
