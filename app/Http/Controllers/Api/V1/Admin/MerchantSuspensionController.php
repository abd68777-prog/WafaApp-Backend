<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminActionReasonRequest;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Services\Merchant\MerchantState;
use App\Services\Merchant\MerchantSuspension;
use Illuminate\Http\JsonResponse;

/**
 * Suspending a shop for fraud or a breach, and reactivating it (requirements
 * §5.3). Both answer with the shop's subscription as it now stands.
 */
class MerchantSuspensionController extends Controller
{
    public function __construct(
        private readonly MerchantSuspension $suspension,
        private readonly MerchantState $state,
    ) {}

    public function suspend(AdminActionReasonRequest $request, Merchant $merchant): JsonResponse
    {
        $merchant = $this->suspension->suspend($merchant, $request->reason(), $this->admin($request), $request->ip());

        return response()->json(['data' => $this->state->subscription($merchant)]);
    }

    public function reactivate(AdminActionReasonRequest $request, Merchant $merchant): JsonResponse
    {
        $merchant = $this->suspension->reactivate($merchant, $request->reason(), $this->admin($request), $request->ip());

        return response()->json(['data' => $this->state->subscription($merchant)]);
    }

    private function admin(AdminActionReasonRequest $request): AdminUser
    {
        return $request->user();
    }
}
