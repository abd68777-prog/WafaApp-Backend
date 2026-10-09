<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CardCycleStatus;
use App\Enums\MerchantStatus;
use App\Enums\StampMethod;
use App\Enums\SubscriptionPeriodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateMerchantIdentityRequest;
use App\Http\Resources\AdminMerchantResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionPeriodResource;
use App\Models\AdminUser;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Merchant;
use App\Models\SubscriptionPeriod;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionLedger;
use App\Services\Merchant\MerchantState;
use App\Support\CursorPage;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The shops (requirements §5.3): a filtered list, a page per shop, and
 * correcting the business name or type. Shops still registering are listed
 * too, with `registration_step` telling the dashboard which screen they
 * stopped at — a shop with no package yet has no status.
 */
class MerchantController extends Controller
{
    /**
     * How many recent payments the shop's page shows; the full history is in
     * the payments screen.
     */
    private const RECENT_PAYMENTS = 10;

    public function __construct(private readonly SubscriptionLedger $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(MerchantStatus::class)],
            'governorate_id' => ['sometimes', 'integer'],
            'business_type_id' => ['sometimes', 'integer'],
            'package_id' => ['sometimes', 'integer'],
            'trial_ending' => ['sometimes', Rule::in(['week'])],
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'registration_step' => ['sometimes', Rule::in(['package', 'pin', 'done'])],
        ]);

        $merchants = Merchant::query()
            ->with(['businessType', 'governorate'])
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(isset($validated['governorate_id']), fn ($query) => $query->where('governorate_id', $validated['governorate_id']))
            ->when(isset($validated['business_type_id']), fn ($query) => $query->where('business_type_id', $validated['business_type_id']))
            ->when(isset($validated['package_id']), fn ($query) => $query->whereHas('subscriptionPeriods', fn ($periods) => $periods
                ->where('package_id', $validated['package_id'])
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())))
            ->when(isset($validated['trial_ending']), fn ($query) => $query
                ->where('status', MerchantStatus::Trial)
                ->whereHas('subscriptionPeriods', fn ($periods) => $periods
                    ->where('type', SubscriptionPeriodType::Trial)
                    ->whereBetween('ends_at', [now(), now()->addWeek()])))
            ->when(isset($validated['q']), fn ($query) => $this->search($query, $validated['q']))
            ->when(isset($validated['registration_step']), fn ($query) => match ($validated['registration_step']) {
                'package' => $query->whereNull('status'),
                'pin' => $query->whereNotNull('status')->whereNull('pin_hash'),
                'done' => $query->whereNotNull('status')->whereNotNull('pin_hash'),
            })
            ->orderByDesc('id');

        return CursorPage::respond($request, $merchants, AdminMerchantResource::class, [], function (Collection $page): void {
            $this->loadSubscriptions($page);
        });
    }

    public function show(Merchant $merchant, MerchantState $state): JsonResponse
    {
        $merchant->load(['businessType', 'governorate']);
        $this->loadSubscriptions(collect([$merchant]));

        return response()->json([
            'data' => [
                ...(new AdminMerchantResource($merchant))->resolve(),
                'address' => $merchant->address,
                'last_login_at' => $merchant->last_login_at?->toIso8601ZuluString(),
                'subscription' => $state->subscription($merchant),
                'periods' => SubscriptionPeriodResource::collection(
                    $merchant->subscriptionPeriods()->with('package')->orderByDesc('starts_at')->orderByDesc('id')->get()
                )->resolve(),
                'payments' => PaymentResource::collection(
                    $merchant->payments()->with('package')->latest('id')->limit(self::RECENT_PAYMENTS)->get()
                )->resolve(),
                'cards' => $this->cards($merchant),
                'stats' => $this->stats($merchant),
            ],
        ]);
    }

    public function update(UpdateMerchantIdentityRequest $request, Merchant $merchant, AuditLogger $audit, MerchantState $state): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $changes = $request->safe()->only(['business_name', 'business_type_id']);

        DB::transaction(function () use ($merchant, $changes, $request, $admin, $audit): void {
            $merchant->fill($changes);

            if (! $merchant->isDirty()) {
                return;
            }

            $before = array_intersect_key($merchant->getOriginal(), $merchant->getDirty());
            $after = $merchant->getDirty();
            $merchant->save();

            $audit->record($admin, 'merchant.identity_updated', $merchant, $before, [
                ...$after,
                'reason' => $request->string('reason')->trim()->value(),
            ], $request->ip());
        });

        return $this->show($merchant->refresh(), $state);
    }

    /**
     * Part of the business name or email, or the whole phone number in any of
     * the forms a person types it.
     */
    private function search(mixed $query, string $term): void
    {
        $phone = PhoneNumber::normalize($term);

        if ($phone !== null) {
            $query->where('phone', $phone);

            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn ($inner) => $inner->where('business_name', 'like', $like)->orWhere('email', 'like', $like));
    }

    /**
     * Each shop's current period and where its subscription ends, from one
     * query for the whole page.
     *
     * @param  Collection<int, Merchant>  $merchants
     */
    private function loadSubscriptions(Collection $merchants): void
    {
        $periods = SubscriptionPeriod::query()
            ->with('package')
            ->whereIn('merchant_id', $merchants->pluck('id'))
            ->get()
            ->groupBy('merchant_id');

        foreach ($merchants as $merchant) {
            $own = $periods->get($merchant->id, collect());

            $merchant->setRelation('activePeriod', $this->ledger->activeAmong($own));
            $merchant->setAttribute('subscription_ends_at', $own->max('ends_at'));
        }
    }

    /**
     * Active and suspended cards alike, each with how many customers hold it.
     *
     * @return list<array<string, mixed>>
     */
    private function cards(Merchant $merchant): array
    {
        $customers = CardCycle::query()
            ->selectRaw('card_id, count(distinct customer_id) as total')
            ->where('merchant_id', $merchant->id)
            ->groupBy('card_id')
            ->pluck('total', 'card_id');

        return $merchant->cards()
            ->with('icon:id,key')
            ->orderBy('id')
            ->get()
            ->map(fn (Card $card): array => [
                'id' => $card->id,
                'name' => $card->name,
                'icon_key' => $card->icon->key,
                'stamps_required' => $card->stamps_required,
                'reward_description' => $card->reward_description,
                'terms' => $card->terms,
                'status' => $card->status->value,
                'customers_count' => (int) ($customers[$card->id] ?? 0),
                'created_at' => $card->created_at->toIso8601ZuluString(),
                'suspended_at' => $card->suspended_at?->toIso8601ZuluString(),
            ])
            ->all();
    }

    /**
     * Totals since the shop opened: customers, stamps (and how many were
     * added by typing the number) and rewards. Cancelled stamps never count.
     *
     * @return array{customers_total: int, stamps_total: int, stamps_via_phone_total: int, rewards_ready: int, rewards_redeemed_total: int, campaigns_total: int}
     */
    private function stats(Merchant $merchant): array
    {
        $stamps = $merchant->stamps()
            ->whereNull('cancelled_at')
            ->selectRaw('method, count(*) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $cycles = $merchant->cardCycles()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'customers_total' => $merchant->cardCycles()->distinct()->count('customer_id'),
            'stamps_total' => (int) $stamps->sum(),
            'stamps_via_phone_total' => (int) ($stamps[StampMethod::Phone->value] ?? 0),
            'rewards_ready' => (int) ($cycles[CardCycleStatus::RewardReady->value] ?? 0),
            'rewards_redeemed_total' => (int) ($cycles[CardCycleStatus::Redeemed->value] ?? 0),
            'campaigns_total' => $merchant->campaigns()->count(),
        ];
    }
}
