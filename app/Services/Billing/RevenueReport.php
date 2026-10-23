<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Merchant\MerchantState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What came in: approved payments between two days, counted on the day they
 * were approved (requirements §6.6). The amounts are the ones copied into
 * each payment at upload, so changing a price never rewrites an old report.
 *
 * Days and months follow Damascus time; amounts are decimal strings, summed
 * in cents so no rounding creeps in.
 */
final class RevenueReport
{
    /**
     * @return array{from: string, to: string, totals: array<string, int|string>, by_month: list<array<string, mixed>>, by_package: list<array<string, mixed>>, by_method: list<array<string, mixed>>}
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $payments = $this->approved($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => $this->sum($payments),
            'by_month' => $payments
                ->groupBy(fn (Payment $payment) => $payment->reviewed_at->setTimezone(MerchantState::TIMEZONE)->format('Y-m'))
                ->sortKeys()
                ->map(fn (Collection $group, string $month) => ['month' => $month, ...$this->sum($group)])
                ->values()
                ->all(),
            'by_package' => $payments
                ->groupBy('package_id')
                ->map(fn (Collection $group) => [
                    'package' => ['id' => $group->first()->package->id, 'name' => $group->first()->package->name],
                    ...$this->sum($group),
                ])
                ->sortBy('package.id')
                ->values()
                ->all(),
            'by_method' => $payments
                ->groupBy(fn (Payment $payment) => $payment->method->value)
                ->sortKeys()
                ->map(fn (Collection $group, string $method) => ['method' => $method, ...$this->sum($group)])
                ->values()
                ->all(),
        ];
    }

    /**
     * The current month in Damascus, up to now.
     *
     * @return array{month: string, count: int, price_usd: string, amount_syp: string}
     */
    public function thisMonth(): array
    {
        $now = CarbonImmutable::now(MerchantState::TIMEZONE);

        return ['month' => $now->format('Y-m'), ...$this->sum($this->approved($now->startOfMonth(), $now))];
    }

    /**
     * @return Collection<int, Payment>
     */
    private function approved(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Payment::query()
            ->with('package:id,name')
            ->where('status', PaymentStatus::Approved)
            ->whereBetween('reviewed_at', [
                $from->setTimezone(MerchantState::TIMEZONE)->startOfDay()->utc(),
                $to->setTimezone(MerchantState::TIMEZONE)->endOfDay()->utc(),
            ])
            ->get(['id', 'package_id', 'method', 'price_usd', 'amount_syp', 'reviewed_at']);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array{count: int, price_usd: string, amount_syp: string}
     */
    private function sum(Collection $payments): array
    {
        return [
            'count' => $payments->count(),
            'price_usd' => $this->format($payments->sum(fn (Payment $payment) => $this->cents($payment->price_usd))),
            'amount_syp' => $this->format($payments->sum(fn (Payment $payment) => $this->cents($payment->amount_syp))),
        ];
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function format(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
