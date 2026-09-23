<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\MerchantStatus;
use App\Enums\SubscriptionPeriodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\RegisterBusinessRequest;
use App\Http\Requests\Api\V1\Merchant\SelectPackageRequest;
use App\Http\Requests\Api\V1\Merchant\SetPinRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\TrialEmailHash;
use App\Services\Clerk\ClerkSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The three registration screens of the merchant app: business details, then
 * the package (which starts the free trial), then the PIN.
 *
 * Each step is its own endpoint so the app can resume where the merchant left
 * off, and `GET /merchant/auth/me` says which step is next.
 */
class RegistrationController extends Controller
{
    /**
     * Step 1 — create the shop for the signed-in Clerk user.
     */
    public function business(RegisterBusinessRequest $request): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);
        $clerkUserId = $session->userId;

        if (Merchant::query()->where('clerk_user_id', $clerkUserId)->exists()) {
            return response()->json(['message' => 'This account already has a registered business.'], 409);
        }

        $attributes = $request->safe()->only('business_name', 'business_type_id', 'governorate_id', 'address', 'owner_name', 'phone');

        if ($request->hasFile('logo')) {
            $attributes['logo_path'] = $request->file('logo')->store('merchants/logos', 'public');
        }

        try {
            $merchant = new Merchant($attributes);
            $merchant->forceFill([
                'clerk_user_id' => $clerkUserId,
                'email' => $session->email !== null ? Str::lower($session->email) : null,
            ])->save();
        } catch (UniqueConstraintViolationException $exception) {
            // Two requests raced past the check above; the unique index is the
            // final guard.
            if (Merchant::query()->where('clerk_user_id', $clerkUserId)->exists()) {
                return response()->json(['message' => 'This account already has a registered business.'], 409);
            }

            throw $exception;
        }

        return $this->merchantResponse($merchant, 201);
    }

    /**
     * Step 2 — choosing a package starts the free trial immediately.
     *
     * The trial is once per merchant, protected by a hashed fingerprint of the
     * Clerk email that outlives the account. Without a trial the shop starts
     * expired and continues through the payment screen.
     */
    public function package(SelectPackageRequest $request): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);
        $merchant = $this->merchantFor($session->userId);

        if ($merchant->status !== null) {
            return response()->json(['message' => 'A package has already been chosen for this business.'], 409);
        }

        $package = Package::query()->findOrFail($request->integer('package_id'));
        $trialGranted = $session->email === null || ! TrialEmailHash::alreadyUsed($session->email);

        DB::transaction(function () use ($merchant, $package, $session, $trialGranted): void {
            if ($trialGranted) {
                $days = (int) Setting::read('trial_days', 14);

                $merchant->subscriptionPeriods()->create([
                    'package_id' => $package->id,
                    'type' => SubscriptionPeriodType::Trial,
                    'duration_months' => null,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays($days),
                    'grace_ends_at' => null,
                ]);

                if ($session->email !== null) {
                    TrialEmailHash::query()->firstOrCreate([
                        'email_hash' => TrialEmailHash::fingerprint($session->email),
                    ]);
                }
            }

            $merchant->forceFill([
                'status' => $trialGranted ? MerchantStatus::Trial : MerchantStatus::Expired,
            ])->save();
        });

        return $this->merchantResponse($merchant->refresh(), 200, ['trial_granted' => $trialGranted]);
    }

    /**
     * Step 3 — the owner sets the PIN that guards the sensitive tabs.
     */
    public function pin(SetPinRequest $request): JsonResponse
    {
        $merchant = $this->merchantFor(ClerkSession::fromRequest($request)->userId);

        if ($merchant->status === null) {
            return response()->json(['message' => 'Choose a package before setting a PIN.'], 409);
        }

        $merchant->forceFill(['pin_hash' => Hash::make($request->string('pin')->value())])->save();

        return $this->merchantResponse($merchant, 200);
    }

    private function merchantFor(string $clerkUserId): Merchant
    {
        $merchant = Merchant::query()->where('clerk_user_id', $clerkUserId)->first();

        abort_if($merchant === null, 403, 'Complete your business registration first.');

        return $merchant;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function merchantResponse(Merchant $merchant, int $status, array $extra = []): JsonResponse
    {
        $merchant->load(['businessType', 'governorate', 'subscriptionPeriods.package']);

        return response()->json([
            'registration_step' => $merchant->registrationStep(),
            'data' => new MerchantResource($merchant),
            ...$extra,
        ], $status);
    }
}
