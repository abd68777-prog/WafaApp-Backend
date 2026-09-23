<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use App\Services\Merchant\PinUnlockToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the PIN-protected tabs of the merchant app: statistics, customers,
 * campaigns, cards, subscription and settings (requirements §6.5).
 *
 * The owner and the cashier share one Clerk account, so the Clerk session
 * alone cannot tell them apart; the PIN is what can. Runs after
 * `clerk.merchant`, which resolved the merchant.
 */
class EnsurePinUnlocked
{
    public function __construct(private readonly PinUnlockToken $pinUnlockToken) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        if (! $this->pinUnlockToken->isValidFor($merchant, $request->header('X-Pin-Token'))) {
            return response()->json([
                'message' => 'Enter the PIN to open this section.',
                'code' => 'pin_required',
            ], 403);
        }

        return $next($request);
    }
}
