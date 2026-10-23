<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Enums\DeletionSubjectType;
use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionPeriodType;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\DeletionRequest;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use App\Services\Billing\RevenueReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The dashboard's first screen (requirements §6.6), for every role: shops by
 * status, trials ending this week, and what needs someone's attention. The
 * money figures are only filled in for roles that see the financials.
 */
class OverviewController extends Controller
{
    private const TRIALS_LISTED = 10;

    private const NEW_DELETION_DAYS = 7;

    public function __invoke(Request $request, RevenueReport $revenue): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        return response()->json(['data' => [
            'merchants' => $this->merchants(),
            'trials_ending_this_week' => $this->trialsEndingThisWeek(),
            'alerts' => $this->alerts(),
            'finance' => $admin->hasPermission(AdminPermission::ViewFinancials) ? $revenue->thisMonth() : null,
        ]]);
    }

    /**
     * @return array{total: int, registering: int, by_status: array<string, int>}
     */
    private function merchants(): array
    {
        $counts = Merchant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];

        foreach (MerchantStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'total' => (int) $counts->sum(),
            // Signed up but no package chosen yet.
            'registering' => (int) ($counts[''] ?? 0),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Same shops as the merchant list's `trial_ending=week` filter.
     *
     * @return array{count: int, merchants: list<array{id: int, business_name: string, trial_ends_at: string}>}
     */
    private function trialsEndingThisWeek(): array
    {
        $periods = SubscriptionPeriod::query()
            ->with('merchant:id,business_name')
            ->where('type', SubscriptionPeriodType::Trial)
            ->whereBetween('ends_at', [now(), now()->addWeek()])
            ->whereHas('merchant', fn ($merchant) => $merchant->where('status', MerchantStatus::Trial))
            ->orderBy('ends_at');

        return [
            'count' => (clone $periods)->count(),
            'merchants' => $periods->limit(self::TRIALS_LISTED)->get()
                ->map(fn (SubscriptionPeriod $period) => [
                    'id' => $period->merchant->id,
                    'business_name' => $period->merchant->business_name,
                    'trial_ends_at' => $period->ends_at->toIso8601ZuluString(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function alerts(): array
    {
        $pending = Payment::query()->where('status', PaymentStatus::Pending);

        return [
            'payments_pending' => (clone $pending)->count(),
            'payments_overdue' => (clone $pending)->where('created_at', '<', Payment::reviewOverdueBefore())->count(),
            // The same receipt must not be accepted twice (requirements §5.2).
            'payments_duplicate_reference' => (clone $pending)
                ->whereNotNull('reference')
                ->whereExists(fn ($earlier) => $earlier->from('payments as earlier')
                    ->whereColumn('earlier.reference', 'payments.reference')
                    ->whereColumn('earlier.id', '!=', 'payments.id'))
                ->count(),
            'deletion_requests_new' => DeletionRequest::query()
                ->where('requested_at', '>=', now()->subDays(self::NEW_DELETION_DAYS))
                ->count(),
            'merchants_pending_deletion' => DeletionRequest::query()
                ->where('subject_type', DeletionSubjectType::Merchant)
                ->whereNull('executed_at')
                ->whereNull('cancelled_at')
                ->count(),
        ];
    }
}
