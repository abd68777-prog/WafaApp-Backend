<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\Clerk\ClerkSession;
use App\Services\Merchant\MerchantState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Merchants sign in through Clerk. Signing in only proves who the user is;
 * the shop itself is created through the registration steps.
 */
class AuthController extends Controller
{
    /**
     * The app's first request on start (contract merchantGetMe): the
     * registration step decides the screen, the subscription the banner.
     *
     * Works before registration is complete. It also records the visit and
     * keeps the stored email in step with the one the merchant signs in with.
     */
    public function me(Request $request, MerchantState $state): JsonResponse
    {
        $session = ClerkSession::fromRequest($request);
        $email = $session->email !== null ? Str::lower($session->email) : null;

        $merchant = Merchant::query()->where('clerk_user_id', $session->userId)->first();

        $merchant?->forceFill([
            'last_login_at' => now(),
            'email' => $email ?? $merchant->email,
        ])->save();

        return response()->json(['data' => $state->me($merchant, $email)]);
    }
}
