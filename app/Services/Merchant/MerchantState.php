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
use App\Services\Billing\SubscriptionLedger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
     * The week campaigns and weekly statistics count in: a calendar week,
     * Saturday 00:00 to Friday 23:59 in Damascus (contract open item 1,
     * decided).
     */
    public const CAMPAIGN_WEEK_STARTS_ON = Carbon::SATURDAY;

    public const TIMEZONE = 'Asia/Damascus';

    public function __construct(private readonly SubscriptionLedger $ledger) {}

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
            'days_remaining' => $this->daysRemaining($merchant),
            'capabilities' => $this->capabilities($merchant->status),
            'banner' => $this->banner($merchant),
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
     * The period that decides the package and its limits: the one covering
     * now, not a renewal paid in advance.
     */
    public function currentPeriod(Merchant $merchant): ?SubscriptionPeriod
    {
        return $this->ledger->activePeriod($merchant);
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
     * When the countdown ends: the whole subscription, renewals included,
     * or the grace days. Null in the other statuses.
     */
    private function countdownEnd(Merchant $merchant): ?CarbonInterface
    {
        return match ($merchant->status) {
            MerchantStatus::Trial, MerchantStatus::Active => $this->ledger->chainEnd($merchant),
            MerchantStatus::Grace => $this->ledger->lastPeriod($merchant)?->grace_ends_at,
            default => null,
        };
    }

    /**
     * Days until the trial or the subscription ends, or until the grace days
     * run out. Null in the other statuses.
     */
    private function daysRemaining(Merchant $merchant): ?int
    {
        $endsAt = $this->countdownEnd($merchant);

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
    private function banner(Merchant $merchant): ?array
    {
        $daysRemaining = $this->daysRemaining($merchant);
        $endsAt = $this->countdownEnd($merchant)?->toIso8601ZuluString();

        [$code, $level, $params] = match (true) {
            $merchant->status === MerchantStatus::Trial && $daysRemaining !== null && $daysRemaining <= self::ENDING_SOON_DAYS => [
                'TRIAL_ENDING', 'warning', ['days_remaining' => $daysRemaining, 'ends_at' => $endsAt],
            ],
            $merchant->status === MerchantStatus::Active && $daysRemaining !== null && $daysRemaining <= self::ENDING_SOON_DAYS => [
                'SUBSCRIPTION_ENDING', 'warning', ['days_remaining' => $daysRemaining, 'ends_at' => $endsAt],
            ],
            $merchant->status === MerchantStatus::Grace => [
                'GRACE', 'danger', ['days_remaining' => $daysRemaining, 'ends_at' => $endsAt],
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
