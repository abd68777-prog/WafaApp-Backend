<?php

namespace App\Services\Merchant;

use App\Enums\CardCycleStatus;
use App\Enums\StampMethod;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Merchant;
use App\Models\Stamp;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The statistics tab (contract §5.5): totals, the same per card, and a daily
 * series for the chart. Days and the week are counted in Damascus, the week
 * from Saturday as for campaigns. Cancelled stamps never count.
 *
 * Stamps and hand-overs of the period are read with a few columns and grouped
 * here, so the day boundaries are Damascus ones whatever the database.
 */
final class MerchantStatistics
{
    public function __construct(private readonly MerchantState $state) {}

    /**
     * @return array<string, mixed>
     */
    public function of(Merchant $merchant, ?Card $card, int $days): array
    {
        $today = CarbonImmutable::now(MerchantState::TIMEZONE)->startOfDay();
        $weekStart = $this->state->campaignWeek()['starts_at'];
        $seriesStart = $today->subDays($days - 1);
        $since = $seriesStart->utc()->min($weekStart);

        $cards = $card ? collect([$card]) : $merchant->cards()->orderBy('id')->get(['id', 'name', 'status']);
        $cardIds = $cards->pluck('id')->all();

        $stamps = Stamp::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('card_id', $cardIds)
            ->whereNull('cancelled_at')
            ->where('stamped_at', '>=', $since)
            ->get(['card_id', 'method', 'stamped_at']);

        $cycles = CardCycle::query()->where('merchant_id', $merchant->id)->whereIn('card_id', $cardIds);
        $customers = (clone $cycles)->selectRaw('card_id, count(distinct customer_id) as total')->groupBy('card_id')->pluck('total', 'card_id');
        $statuses = (clone $cycles)->selectRaw('card_id, status, count(*) as total')->groupBy('card_id', 'status')->get();
        $redeemedAt = (clone $cycles)->where('redeemed_at', '>=', $seriesStart->utc())->pluck('redeemed_at');

        $block = fn (Collection $stamps, int $customersTotal, Collection $statuses): array => [
            'customers_total' => $customersTotal,
            'stamps_today' => $stamps->filter(fn (Stamp $stamp): bool => $stamp->stamped_at->gte($today))->count(),
            'stamps_this_week' => $stamps->filter(fn (Stamp $stamp): bool => $stamp->stamped_at->gte($weekStart))->count(),
            'stamps_via_phone_today' => $stamps->filter(fn (Stamp $stamp): bool => $stamp->method === StampMethod::Phone && $stamp->stamped_at->gte($today))->count(),
            'rewards_ready' => (int) $statuses->where('status', CardCycleStatus::RewardReady)->sum('total'),
            'rewards_redeemed_total' => (int) $statuses->where('status', CardCycleStatus::Redeemed)->sum('total'),
        ];

        return [
            'generated_at' => now()->toIso8601ZuluString(),
            'totals' => $block(
                $stamps,
                (int) (clone $cycles)->distinct()->count('customer_id'),
                $statuses,
            ),
            'per_card' => $cards->map(fn (Card $card): array => [
                'card_id' => $card->id,
                'card_name' => $card->name,
                'status' => $card->status->value,
                ...$block(
                    $stamps->where('card_id', $card->id),
                    (int) ($customers[$card->id] ?? 0),
                    $statuses->where('card_id', $card->id),
                ),
            ])->values()->all(),
            'daily' => $this->daily($seriesStart, $days, $stamps, $redeemedAt),
        ];
    }

    /**
     * One entry per Damascus day, oldest first.
     *
     * @param  Collection<int, Stamp>  $stamps
     * @param  Collection<int, mixed>  $redeemedAt
     * @return list<array{date: string, stamps: int, stamps_via_phone: int, rewards_redeemed: int}>
     */
    private function daily(CarbonImmutable $start, int $days, Collection $stamps, Collection $redeemedAt): array
    {
        $day = fn ($moment): string => CarbonImmutable::parse($moment)->setTimezone(MerchantState::TIMEZONE)->toDateString();
        $stampsByDay = $stamps->groupBy(fn (Stamp $stamp): string => $day($stamp->stamped_at));
        $redeemedByDay = $redeemedAt->countBy(fn ($moment): string => $day($moment));

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $ofDay = $stampsByDay->get($date, collect());

            $series[] = [
                'date' => $date,
                'stamps' => $ofDay->count(),
                'stamps_via_phone' => $ofDay->where('method', StampMethod::Phone)->count(),
                'rewards_redeemed' => (int) ($redeemedByDay[$date] ?? 0),
            ];
        }

        return $series;
    }
}
