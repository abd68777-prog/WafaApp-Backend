<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\CardStatus;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\StoreCardRequest;
use App\Http\Resources\MerchantCardResource;
use App\Models\Card;
use App\Models\Merchant;
use App\Services\Merchant\MerchantState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The shop's cards (contract §5.8). A published card is never edited, only
 * suspended: its reward and stamp count are a promise to customers who
 * already started collecting, so there is no update route at all.
 */
class CardController extends Controller
{
    /**
     * Every card, active first; the list is small, so it is not paginated.
     * Open without the PIN because the scan screen picks a card from it.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(CardStatus::class)],
        ]);

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $cards = $merchant->cards()
            ->with('icon')
            ->withMerchantCounts()
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CardStatus::Active->value])
            ->latest('id')
            ->get();

        return MerchantCardResource::collection($cards);
    }

    /**
     * Publish a card, within the package's limit of active cards.
     */
    public function store(StoreCardRequest $request, MerchantState $state): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        if (! $merchant->status->canCreateCards()) {
            throw ApiException::of(ErrorCode::MerchantStatusBlocksAction, 'The subscription does not allow new cards now.', [
                'status' => $merchant->status->value,
                'action' => 'cards',
            ]);
        }

        $cardsLimit = $state->cardsLimit($merchant);

        if ($state->activeCardsCount($merchant) >= $cardsLimit) {
            throw ApiException::of(ErrorCode::CardsLimitReached, 'The package allows no more active cards.', [
                'cards_limit' => $cardsLimit,
            ]);
        }

        $card = $merchant->cards()->make($request->safe()->only('name', 'stamps_required', 'reward_description', 'terms', 'icon_id'));
        $card->forceFill(['status' => CardStatus::Active])->save();

        return $this->cardResponse($card, 201);
    }

    /**
     * Final: there is no route back. Customers already collecting finish
     * their cycle and get their reward; nobody new joins and no new cycle
     * opens. Suspending twice returns the card unchanged.
     */
    public function suspend(Request $request, Card $card): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        abort_unless($card->merchant_id === $merchant->id, 404);

        if ($card->status !== CardStatus::Suspended) {
            $card->forceFill(['status' => CardStatus::Suspended, 'suspended_at' => now()])->save();
        }

        return $this->cardResponse($card, 200);
    }

    private function cardResponse(Card $card, int $status): JsonResponse
    {
        $card = Card::query()->with('icon')->withMerchantCounts()->findOrFail($card->id);

        return (new MerchantCardResource($card))->response()->setStatusCode($status);
    }
}
