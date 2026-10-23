<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Payment;
use App\Services\Billing\RevenueReport;
use App\Services\Merchant\MerchantState;
use App\Support\CursorPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The payment history and financial reports (requirements §6.6), for the
 * Super Admin and the payments reviewer. Looking only: deciding a payment
 * stays with the reviewer's queue.
 *
 * Dates are days in Damascus time.
 */
class FinanceController extends Controller
{
    public function payments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'merchant_id' => ['sometimes', 'integer'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $payments = Payment::query()
            ->with(['package', 'merchant', 'reviewedBy'])
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['method']), fn ($query) => $query->where('method', $validated['method']))
            ->when(isset($validated['merchant_id']), fn ($query) => $query->where('merchant_id', $validated['merchant_id']))
            ->when(isset($validated['from']), fn ($query) => $query->where('created_at', '>=', $this->day($validated['from'])->startOfDay()->utc()))
            ->when(isset($validated['to']), fn ($query) => $query->where('created_at', '<=', $this->day($validated['to'])->endOfDay()->utc()))
            ->orderByDesc('id');

        return CursorPage::respond($request, $payments, AdminPaymentResource::class);
    }

    /**
     * Approved payments between two days, this month so far by default.
     */
    public function report(Request $request, RevenueReport $revenue): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $today = CarbonImmutable::now(MerchantState::TIMEZONE);
        $from = isset($validated['from']) ? $this->day($validated['from']) : $today->startOfMonth();
        $to = isset($validated['to']) ? $this->day($validated['to']) : $today;

        return response()->json(['data' => $revenue->between($from, $to)]);
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date, MerchantState::TIMEZONE)->startOfDay();
    }
}
