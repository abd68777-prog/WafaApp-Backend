<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\ErrorCode;
use App\Enums\MerchantStatus;
use App\Enums\SubscriptionPeriodType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\RegisterBusinessRequest;
use App\Http\Requests\Api\V1\Merchant\SelectPackageRequest;
use App\Http\Requests\Api\V1\Merchant\SetPinRequest;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Setting;
use App\Models\TrialEmailHash;
use App\Services\Clerk\ClerkSession;
use App\Services\Merchant\MerchantState;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The three registration screens of the merchant app: business details, then
 * the package (which starts the free trial), then the PIN (contract §5.2).
 *
 * Each step is its own endpoint so the app can resume where the merchant left
 * off; `GET /merchant/me` says which step is next, and a step sent out of turn
 * is refused with REGISTRATION_STEP_MISMATCH.
 */
class RegistrationController extends Controller
{
    public function __construct(private readonly MerchantState $state) {}

    /**
     * Step 1 — create the shop for the signed-in Clerk user.
     */
    public function business(RegisterBusinessRequest $request): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);
        $this->expectStep($this->merchantFor($session), 'business');

        $attributes = $request->safe()->only('business_name', 'business_type_id', 'governorate_id', 'address', 'owner_name', 'phone');

        if ($request->hasFile('logo')) {
            $attributes['logo_path'] = $request->file('logo')->store('merchants/logos', config('filesystems.media_disk'))
                ?: throw new RuntimeException('The logo could not be stored.');
        }

        try {
            $merchant = new Merchant($attributes);
            $merchant->forceFill([
                'clerk_user_id' => $session->userId,
                'email' => $this->email($session),
            ])->save();
        } catch (UniqueConstraintViolationException $exception) {
            // Two requests raced past the step check; the unique index on the
            // Clerk user is the final guard.
            $this->expectStep($this->merchantFor($session), 'business');

            throw $exception;
        }

        return $this->response($merchant, $session, 201);
    }

    /**
     * Step 2 — choosing a package starts the free trial immediately.
     *
     * The trial is once per merchant, protected by a hashed fingerprint of the
     * Clerk email that outlives the account. Without a trial the request still
     * succeeds: the shop starts expired and goes to payment after the PIN.
     */
    public function package(SelectPackageRequest $request): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);
        $merchant = $this->merchantFor($session);
        $this->expectStep($merchant, 'package');

        $package = Package::query()->findOrFail($request->integer('package_id'));
        $trialGranted = $session->email === null || ! TrialEmailHash::alreadyUsed($session->email);

        DB::transaction(function () use ($merchant, $package, $session, $trialGranted): void {
            if ($trialGranted) {
                $merchant->subscriptionPeriods()->create([
                    'package_id' => $package->id,
                    'type' => SubscriptionPeriodType::Trial,
                    'duration_months' => null,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays((int) Setting::read('trial_days', 14)),
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

        return $this->response($merchant->refresh(), $session, 200, ['trial_granted' => $trialGranted]);
    }

    /**
     * Step 3 — the owner sets the PIN that guards the sensitive tabs. The
     * unlock token comes back too, so the PIN is not asked for right away.
     */
    public function pin(SetPinRequest $request, PinUnlockToken $pinUnlockToken): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);
        $merchant = $this->merchantFor($session);
        $this->expectStep($merchant, 'pin');

        $merchant->forceFill(['pin_hash' => Hash::make($request->string('pin')->value())])->save();

        return response()->json([
            'data' => [
                'me' => $this->state->me($merchant, $this->email($session)),
                'pin' => $pinUnlockToken->describe($merchant),
            ],
        ]);
    }

    private function merchantFor(ClerkSession $session): ?Merchant
    {
        return Merchant::query()->where('clerk_user_id', $session->userId)->first();
    }

    /**
     * @throws ApiException REGISTRATION_STEP_MISMATCH with the step to open.
     */
    private function expectStep(?Merchant $merchant, string $step): void
    {
        $current = $merchant?->registrationStep() ?? 'business';

        if ($current !== $step) {
            throw ApiException::of(ErrorCode::RegistrationStepMismatch, "Registration is at the {$current} step.", [
                'registration_step' => $current,
            ]);
        }
    }

    private function email(ClerkSession $session): ?string
    {
        return $session->email !== null ? Str::lower($session->email) : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function response(Merchant $merchant, ClerkSession $session, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => [...$this->state->me($merchant, $this->email($session)), ...$extra],
        ], $status);
    }
}
