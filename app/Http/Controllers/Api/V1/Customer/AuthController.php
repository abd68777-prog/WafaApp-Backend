<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\CardCycleStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RequestOtpRequest;
use App\Http\Requests\Api\V1\Customer\VerifyOtpRequest;
use App\Http\Resources\CardSummaryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\MerchantSummaryResource;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Setting;
use App\Services\Customer\CustomerQrCode;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Customer authentication: phone number plus a one-time code over WhatsApp
 * (contract §4.1).
 *
 * Signing in is required before anything else in the app, because stamps are
 * tied to the phone number and there is nothing to show without an account.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly CustomerQrCode $qrCode,
    ) {}

    /**
     * Send a verification code to the phone number.
     *
     * The response is identical for a known and an unknown number, so the
     * endpoint cannot be used to discover who is registered.
     */
    public function requestCode(RequestOtpRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->otp->issue($request->phoneE164(), $request->ip()),
        ], 202);
    }

    /**
     * Exchange a valid code for an API token.
     *
     * Creates the account for a new number, or completes the pending customer
     * a merchant created from the same number (transition 06), and records the
     * consent to the policy version the customer saw.
     */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $policyVersion = $request->string('policy_version')->value();

        if ($policyVersion !== Setting::currentPolicyVersion()) {
            throw ApiException::of(ErrorCode::PolicyVersionOutdated, 'The privacy policy was updated; reload it.', [
                'current_version' => Setting::currentPolicyVersion(),
            ]);
        }

        $phone = $request->phoneE164();

        // Checked before the account is looked up, so owning the number is
        // what unlocks any information about the account behind it.
        $otpCode = $this->otp->verify($phone, $request->string('code')->value());

        $customer = Customer::query()->where('phone', $phone)->first();
        $wasPending = $customer?->isPending() ?? false;

        $customer = DB::transaction(function () use ($customer, $phone, $otpCode, $policyVersion): Customer {
            $this->otp->consume($otpCode);

            $customer ??= new Customer(['phone' => $phone]);

            if ($customer->isPending()) {
                $customer->forceFill(['registered_at' => now()]);
                $this->qrCode->assignTo($customer);
            }

            $customer->forceFill([
                'last_login_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            $customer->policyConsents()->firstOrCreate(
                ['policy_version' => $policyVersion],
                ['consented_at' => now()],
            );

            // Refreshed so columns filled by database defaults (campaigns_muted)
            // come back with their stored values, not null.
            return $customer->refresh();
        });

        return response()->json([
            'data' => [
                'token' => $customer->createToken('customer-app', ['customer'])->plainTextToken,
                'token_type' => 'Bearer',
                'customer' => new CustomerResource($customer),
                'needs_profile' => ! $customer->hasCompleteProfile(),
                // Feeds the "your stamps arrived" screen after signing up with a
                // number a merchant had already been stamping.
                'claimed_stamps' => $wasPending ? $this->claimedStamps($customer) : [],
            ],
        ]);
    }

    /**
     * Revoke the token used for this request. The app removes its push token
     * first, through `DELETE /customer/devices/{token}`.
     */
    public function logout(Request $request): Response
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $customer->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function claimedStamps(Customer $customer): array
    {
        return CardCycle::query()
            ->with(['merchant.businessType', 'merchant.governorate', 'card.icon'])
            ->where('customer_id', $customer->id)
            ->where('stamps_count', '>', 0)
            ->whereIn('status', [CardCycleStatus::Collecting, CardCycleStatus::RewardReady])
            ->orderBy('id')
            ->get()
            ->map(fn (CardCycle $cycle): array => [
                'merchant' => new MerchantSummaryResource($cycle->merchant),
                'card' => new CardSummaryResource($cycle->card),
                'stamps_count' => $cycle->stamps_count,
                'status' => $cycle->status->value,
            ])
            ->all();
    }
}
