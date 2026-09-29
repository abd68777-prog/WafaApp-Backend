<?php

namespace App\Services\Merchant;

use App\Enums\CardStatus;
use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionPeriodType;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\PackageSummaryResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionPeriodResource;
use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use App\Models\TrialEmailHash;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Everything `GET /merchant/me` tells the app about a shop: which screen to
 * open, what the subscription allows right now, the banner to show and how
 * much of the package is used (contract MerchantMe).
 *
 * The status itself is stored on the merchant; this class only reads it, so
 * every endpoint that enforces a capability agrees with what the app shows.
 */
final class MerchantState
{
    /**
     * Banners warn this many days before a trial or a paid period ends — the
     * same notice the reminder notifications give (requirements §7.2).
     */
    public const ENDING_SOON_DAYS = 3;

    /**
     * Campaign limits count per calendar week, Saturday to Friday, in the
     * shop's time zone (contract open item 1, until Deep Code decides).
     */
    public const CAMPAIGN_WEEK_STARTS_ON = Carbon::SATURDAY;

    public const TIMEZONE = 'Asia/Damascus';

    /**
     * @return array<string, mixed>
     */
    public function me(?Merchant $merchant, ?string $email): array
    {
        $merchant?->loadMissing(['businessType', 'governorate']);

        return [
            'registration_step' => $merchant?->registrationStep() ?? 'business',
            'email' => $email,
            'merchant' => $merchant ? new MerchantResource($merchant) : null,
            'subscription' => $merchant ? $this->subscription($merchant) : null,
            'usage' => $merchant?->status !== null ? $this->usage($merchant) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function subscription(Merchant $merchant): ?array
    {
        if ($merchant->status === null) {
            return null;
        }

        $period = $this->currentPeriod($merchant);
        $pendingPayment = $merchant->payments()->with('package')->where('status', PaymentStatus::Pending)->first();

        return [
            'status' => $merchant->status->value,
            'package' => $period ? new PackageSummaryResource($period->package) : null,
            'current_period' => $period ? new SubscriptionPeriodResource($period) : null,
            'trial_used' => $this->trialUsed($merchant),
            'days_remaining' => $this->daysRemaining($merchant, $period),
            'capabilities' => $this->capabilities($merchant->status),
            'banner' => $this->banner($merchant, $period),
            'pending_payment' => $pendingPayment ? new PaymentResource($pendingPayment) : null,
        ];
    }

    /**
     * What the server allows now (requirements table 3.4). The app uses it
     * for display; the endpoints enforce the same rules regardless.
     *
     * @return array{stamps: bool, new_customers: bool, campaigns: bool, redemptions: bool, create_cards: bool, directory_visible: bool}
     */
    public function capabilities(MerchantStatus $status): array
    {
        return [
            'stamps' => $status->canCollectStamps(),
            'new_customers' => $status->canCollectStamps(),
            'campaigns' => $status->canSendCampaigns(),
            'redemptions' => $status->canRedeemRewards(),
            'create_cards' => $status->canCreateCards(),
            'directory_visible' => $status->appearsInDirectory(),
        ];
    }

    /**
     * @return array{active_cards: int, cards_limit: int, campaigns_used_this_week: int, weekly_campaigns_limit: int, campaigns_resets_at: string}
     */
    public function usage(Merchant $merchant): array
    {
        $package = $this->currentPeriod($merchant)?->package;
        $week = $this->campaignWeek();

        return [
            'active_cards' => $this->activeCardsCount($merchant),
            'cards_limit' => $package?->cards_limit ?? 0,
            'campaigns_used_this_week' => $merchant->campaigns()->where('sent_at', '>=', $week['starts_at'])->count(),
            'weekly_campaigns_limit' => $package?->weekly_campaigns_limit ?? 0,
            'campaigns_resets_at' => $week['resets_at']->toIso8601ZuluString(),
        ];
    }

    /**
     * The period that decides the package and the dates: the one ending last.
     */
    public function currentPeriod(Merchant $merchant): ?SubscriptionPeriod
    {
        return $merchant->subscriptionPeriods()->with('package')->orderByDesc('ends_at')->orderByDesc('id')->first();
    }

    /**
     * How many cards the package allows; suspended cards do not count.
     */
    public function cardsLimit(Merchant $merchant): int
    {
        return $this->currentPeriod($merchant)?->package?->cards_limit ?? 0;
    }

    public function activeCardsCount(Merchant $merchant): int
    {
        return $merchant->cards()->where('status', CardStatus::Active)->count();
    }

    /**
     * @return array{starts_at: CarbonImmutable, resets_at: CarbonImmutable}
     */
    public function campaignWeek(): array
    {
        $startsAt = CarbonImmutable::now(self::TIMEZONE)->startOfWeek(self::CAMPAIGN_WEEK_STARTS_ON);

        return [
            'starts_at' => $startsAt->utc(),
            'resets_at' => $startsAt->addWeek()->utc(),
        ];
    }

    /**
     * A shop that had a trial, or whose Clerk email already took one on an
     * earlier account, cannot start another.
     */
    private function trialUsed(Merchant $merchant): bool
    {
        return $merchant->subscriptionPeriods()->where('type', SubscriptionPeriodType::Trial)->exists()
            || ($merchant->email !== null && TrialEmailHash::alreadyUsed($merchant->email));
    }

    /**
     * Days until the trial or the paid period ends, or until the grace days
     * run out. Null in the other statuses.
     */
    private function daysRemaining(Merchant $merchant, ?SubscriptionPeriod $period): ?int
    {
        $endsAt = match ($merchant->status) {
            MerchantStatus::Trial, MerchantStatus::Active => $period?->ends_at,
            MerchantStatus::Grace => $period?->grace_ends_at,
            default => null,
        };

        if ($endsAt === null) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($endsAt, false) / 86400));
    }

    /**
     * The permanent banner at the top of the merchant app. The text comes from
     * the app's resources by `code`.
     *
     * @return array{code: string, level: string, params: object}|null
     */
    private function banner(Merchant $merchant, ?SubscriptionPeriod $period): ?array
    {
        $daysRemaining = $this->daysRemaining($merchant, $period);

        [$code, $level, $params] = match (true) {
            $merchant->status === MerchantStatus::Trial && $daysRemaining !== null && $daysRemaining <= self::ENDING_SOON_DAYS => [
                'TRIAL_ENDING', 'warning', ['days_remaining' => $daysRemaining, 'ends_at' => $period?->ends_at?->toIso8601ZuluString()],
            ],
            $merchant->status === MerchantStatus::Active && $daysRemaining !== null && $daysRemaining <= self::ENDING_SOON_DAYS => [
                'SUBSCRIPTION_ENDING', 'warning', ['days_remaining' => $daysRemaining, 'ends_at' => $period?->ends_at?->toIso8601ZuluString()],
            ],
            $merchant->status === MerchantStatus::Grace => [
                'GRACE', 'danger', ['days_remaining' => $daysRemaining, 'ends_at' => $period?->grace_ends_at?->toIso8601ZuluString()],
            ],
            $merchant->status === MerchantStatus::Expired => ['EXPIRED', 'danger', []],
            $merchant->status === MerchantStatus::Suspended => ['SUSPENDED', 'danger', []],
            $merchant->status === MerchantStatus::PendingDeletion => ['PENDING_DELETION', 'danger', []],
            default => [null, null, []],
        };

        if ($code === null) {
            return null;
        }

        return [
            'code' => $code,
            'level' => $level,
            'params' => (object) array_filter($params, fn (mixed $value): bool => $value !== null),
        ];
    }
}
