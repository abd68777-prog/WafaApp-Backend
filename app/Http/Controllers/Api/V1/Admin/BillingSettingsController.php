<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateBillingSettingsRequest;
use App\Http\Resources\AdminPaymentAccountResource;
use App\Models\AdminUser;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * What the merchant's payment screen shows (the exchange rate, and the
 * payment methods, each changed through PaymentAccountController) and how
 * long trials and grace days last. A change applies from the next trial or
 * payment; running periods keep their dates.
 */
class BillingSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => [...$this->current(), 'payment_methods' => $this->paymentMethods()]]);
    }

    public function update(UpdateBillingSettingsRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        DB::transaction(function () use ($request, $admin, $audit): void {
            $before = $this->current();

            foreach ($request->validated() as $key => $value) {
                Setting::write($key, $key === 'exchange_rate_syp' ? (float) $value : $value ?? '');
            }

            $audit->record($admin, 'settings.billing_updated', null, $before, $this->current(), $request->ip());
        });

        return response()->json(['data' => [...$this->current(), 'payment_methods' => $this->paymentMethods()]]);
    }

    /**
     * Every method, paused ones too; each is changed through its own route.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paymentMethods(): array
    {
        return AdminPaymentAccountResource::collection(
            PaymentAccount::query()->orderBy('sort_order')->orderBy('id')->get()
        )->resolve();
    }

    /**
     * @return array{exchange_rate_syp: string, payment_review_sla_hours: int, trial_days: int, grace_days: int}
     */
    private function current(): array
    {
        return [
            'exchange_rate_syp' => number_format((float) Setting::read('exchange_rate_syp', 0), 2, '.', ''),
            'payment_review_sla_hours' => (int) Setting::read('payment_review_sla_hours', 24),
            'trial_days' => (int) Setting::read('trial_days', 14),
            'grace_days' => (int) Setting::read('grace_days', 3),
        ];
    }
}
