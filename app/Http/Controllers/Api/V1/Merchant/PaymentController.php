<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\CardStatus;
use App\Enums\ErrorCode;
use App\Enums\MerchantStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\SubmitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\CursorPage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Paying for the subscription (contract §5.9): the merchant transfers
 * outside the app, then uploads the proof here and waits for the payments
 * reviewer.
 */
class PaymentController extends Controller
{
    /**
     * The merchant's payments, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        return CursorPage::respond(
            $request,
            $merchant->payments()->with('package')->orderByDesc('id'),
            PaymentResource::class,
        );
    }

    /**
     * The price, the exchange rate and the SYP amount are copied into the
     * payment now, so a price changed before the review never makes the
     * merchant look short (requirements §3.5). One payment may await review
     * at a time.
     */
    public function store(SubmitPaymentRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        if (in_array($merchant->status, [MerchantStatus::Suspended, MerchantStatus::PendingDeletion], true)) {
            throw ApiException::of(ErrorCode::MerchantStatusBlocksAction, 'The account does not take payments now.', [
                'status' => $merchant->status->value,
                'action' => 'payments',
            ]);
        }

        $this->ensureNothingPending($merchant);

        $package = Package::query()->findOrFail($request->integer('package_id'));
        $price = PackagePrice::query()
            ->where('package_id', $package->id)
            ->where('duration_months', $request->integer('duration_months'))
            ->first()
            ?? throw ApiException::of(ErrorCode::PriceNotAvailable, 'This package has no price for this duration.');

        $keepCardIds = $this->cardsToKeep($request, $merchant, $package);
        $exchangeRate = (float) Setting::read('exchange_rate_syp', 0);
        $proofs = Storage::disk(config('filesystems.proofs_disk'));
        $proofPath = $request->file('proof')->store('payments/proofs', config('filesystems.proofs_disk'))
            ?: throw new RuntimeException('The payment proof could not be stored.');

        try {
            $payment = $merchant->payments()->create([
                'package_id' => $package->id,
                'duration_months' => $price->duration_months,
                'price_usd' => $price->price_usd,
                'exchange_rate' => $exchangeRate,
                'amount_syp' => round((float) $price->price_usd * $exchangeRate, 2),
                'method' => $request->enum('method', PaymentMethod::class),
                'reference' => $request->filled('reference') ? $request->string('reference')->trim()->value() : null,
                'proof_path' => $proofPath,
                'keep_card_ids' => $keepCardIds,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another upload got there first.
            $proofs->delete($proofPath);
            $this->ensureNothingPending($merchant);

            throw ApiException::of(ErrorCode::PaymentAlreadyPending, 'A payment is already waiting for review.');
        }

        return (new PaymentResource($payment->refresh()->load('package')))->response()->setStatusCode(201);
    }

    private function ensureNothingPending(Merchant $merchant): void
    {
        $pendingId = $merchant->payments()->where('status', PaymentStatus::Pending)->value('id');

        if ($pendingId !== null) {
            throw ApiException::of(ErrorCode::PaymentAlreadyPending, 'A payment is already waiting for review.', [
                'payment_id' => $pendingId,
            ]);
        }
    }

    /**
     * Moving to a package with fewer cards than the shop runs means choosing
     * which cards stay; the rest are suspended when the new period starts.
     *
     * @return list<int>|null
     */
    private function cardsToKeep(SubmitPaymentRequest $request, Merchant $merchant, Package $package): ?array
    {
        $activeIds = $merchant->cards()->where('status', CardStatus::Active)->pluck('id')->all();

        if (count($activeIds) <= $package->cards_limit) {
            return null;
        }

        $keep = array_map('intval', (array) $request->input('keep_card_ids', []));
        $valid = $keep !== []
            && count($keep) <= $package->cards_limit
            && array_diff($keep, $activeIds) === [];

        if (! $valid) {
            throw ApiException::of(ErrorCode::KeepCardsRequired, 'Choose which cards stay active on the smaller package.', [
                'cards_limit' => $package->cards_limit,
                'active_cards' => count($activeIds),
            ]);
        }

        return array_values($keep);
    }
}
