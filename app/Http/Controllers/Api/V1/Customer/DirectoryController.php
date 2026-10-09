<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\CardStatus;
use App\Enums\MerchantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardSummaryResource;
use App\Http\Resources\MerchantSummaryResource;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The shop directory and a shop's page (contract §4.4).
 *
 * A shop shows only while a customer walking in would get a stamp: its
 * subscription allows stamps and it runs at least one active card
 * (requirements §6.2). Search happens on the phone, so the whole directory
 * goes in one response and no search term ever reaches the server.
 */
class DirectoryController extends Controller
{
    /**
     * The whole directory, with an ETag: the app sends If-None-Match and
     * gets 304 when nothing changed.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $merchants = $this->visible(Merchant::query())
            ->with(['businessType', 'governorate'])
            ->withCount(['cards as active_cards_count' => fn (Builder $cards) => $cards->where('status', CardStatus::Active)])
            ->orderBy('business_name')
            ->orderBy('id')
            ->get();

        $data = $merchants->map(fn (Merchant $merchant): array => [
            ...(new MerchantSummaryResource($merchant))->resolve($request),
            'address' => $merchant->address,
            'active_cards_count' => $merchant->active_cards_count,
        ])->all();

        $etag = '"'.sha1(json_encode($data)).'"';

        if (in_array($etag, $request->getETags(), true)) {
            return response()->noContent(304)->setEtag(trim($etag, '"'));
        }

        return response()->json(['data' => $data])->setEtag(trim($etag, '"'));
    }

    /**
     * A shop's page. A shop missing from the directory — expired, held, or
     * with no active card — still opens for a customer who collected there,
     * without its cards; `accepting_stamps` says whether stamps are taken.
     */
    public function show(Request $request, int $merchant): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $shop = Merchant::query()->whereNotNull('status')->with(['businessType', 'governorate'])->findOrFail($merchant);
        $visible = $this->visible(Merchant::query()->whereKey($shop->id))->exists();

        abort_unless($visible || $customer->cardCycles()->where('merchant_id', $shop->id)->exists(), 404);

        $cards = $visible
            ? $shop->cards()->with('icon')->where('status', CardStatus::Active)->orderBy('id')->get()
            : collect();

        return response()->json(['data' => [
            ...(new MerchantSummaryResource($shop))->resolve($request),
            'address' => $shop->address,
            'accepting_stamps' => $shop->status->canCollectStamps(),
            'muted' => $customer->merchantMutes()->where('merchant_id', $shop->id)->exists(),
            'cards' => CardSummaryResource::collection($cards)->resolve($request),
        ]]);
    }

    /**
     * @param  Builder<Merchant>  $merchants
     * @return Builder<Merchant>
     */
    private function visible(Builder $merchants): Builder
    {
        $statuses = array_filter(MerchantStatus::cases(), fn (MerchantStatus $status): bool => $status->appearsInDirectory());

        return $merchants
            ->whereIn('status', $statuses)
            ->whereHas('cards', fn (Builder $cards) => $cards->where('status', CardStatus::Active));
    }
}
