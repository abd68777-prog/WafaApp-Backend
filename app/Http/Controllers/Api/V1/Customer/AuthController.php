<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\CardCycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RequestOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyOtpRequest;
use App\Http\Resources\CustomerResource;
use App\Models\CardCycle;
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
 * Signing in is required before anything else in the app, because stamps are
 * tied to the phone number and there is nothing to show without an account.
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
     * Exchange a valid code for an API token, completing the account when the
     * caller is new or was created by a merchant from their phone number.
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->phoneE164();

        // Checked before anything else, so owning the number is what unlocks
        // any information about the account behind it.
        $otpCode = $this->otp->verify($phone, $request->string('code')->value());

        $customer = Customer::query()->where('phone', $phone)->first();
        $isNewCustomer = $customer === null || $customer->isPending();

        if ($isNewCustomer) {
            $this->guardAgainstIncompleteProfile($request);
        }

        $customer = DB::transaction(function () use ($customer, $phone, $request, $otpCode, $isNewCustomer): Customer {
            $this->otp->consume($otpCode);

            $customer ??= new Customer(['phone' => $phone]);

            if ($isNewCustomer) {
                $customer->fill($request->safe()->only('name', 'birthdate'));
                $customer->forceFill([
                    'qr_secret' => $customer->qr_secret ?? Str::random(40),
                    'registered_at' => now(),
                ]);
            }

            $customer->forceFill([
                'last_login_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            if ($isNewCustomer) {
                $customer->policyConsents()->firstOrCreate(
                    ['policy_version' => $request->string('policy_version')->value()],
                    ['consented_at' => now()],
                );
            }

            // Refreshed so columns filled by database defaults (campaigns_muted)
            // come back with their stored values, not null.
            return $customer->refresh();
        });

        return response()->json([
            'data' => new CustomerResource($customer),
            'token' => $customer->createToken(
                $request->string('device_name', 'customer-app')->value(),
                ['customer'],
            )->plainTextToken,
            'token_type' => 'Bearer',
            'is_new_customer' => $isNewCustomer,
            // Feeds the "your stamps arrived" screen after signing up with a
            // number a merchant had already been stamping.
            'stamps_waiting' => $isNewCustomer ? $this->stampsWaitingFor($customer) : [],
        ]);
    }

    public function me(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }

    /**
     * Revoke only the token used for the current request, and stop push
     * notifications to this device when the app sends its device token.
     */
    public function logout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['sometimes', 'string', 'max:255'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user();

        $customer->currentAccessToken()->delete();

        if (isset($validated['device_token'])) {
            $customer->deviceTokens()->where('token', $validated['device_token'])->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Revoke every token of the customer and forget every device, so no phone
     * signed in to this account keeps receiving notifications.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $customer->tokens()->delete();
        $customer->deviceTokens()->delete();

        return response()->json(['message' => 'Logged out on all devices.']);
    }

    /**
     * A new account needs a name, a birthdate that clears the age limit, and
     * consent to the current privacy policy. Asked only after the code checked
     * out, and the code stays usable so the customer can resubmit.
     *
     * @throws ValidationException
     */
    private function guardAgainstIncompleteProfile(VerifyOtpRequest $request): void
    {
        $missing = [];

        if (blank($request->input('name'))) {
            $missing['name'] = ['Your name is required to finish creating your account.'];
        }

        if (blank($request->input('birthdate'))) {
            $missing['birthdate'] = ['Your date of birth is required to finish creating your account.'];
        }

        if (blank($request->input('policy_version'))) {
            $missing['policy_version'] = ['You must accept the privacy policy to create an account.'];
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    /**
     * @return list<array{merchant: string, card: string, stamps: int}>
     */
    private function stampsWaitingFor(Customer $customer): array
    {
        return CardCycle::query()
            ->with(['merchant:id,business_name', 'card:id,name'])
            ->where('customer_id', $customer->id)
            ->where('stamps_count', '>', 0)
            ->whereIn('status', [CardCycleStatus::Collecting, CardCycleStatus::RewardReady])
            ->get()
            ->map(fn (CardCycle $cycle): array => [
                'merchant' => $cycle->merchant->business_name,
                'card' => $cycle->card->name,
                'stamps' => $cycle->stamps_count,
            ])
            ->values()
            ->all();
    }
}
