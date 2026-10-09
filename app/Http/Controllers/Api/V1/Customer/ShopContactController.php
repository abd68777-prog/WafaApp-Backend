<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\MerchantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantSummaryResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Every shop working with Wafa right now, with where to find it and how to
 * call it — a window of its own in the customer app, outside the contract's
 * directory so that shape stays as agreed.
 *
 * "Working" means its subscription takes stamps (TRIAL, ACTIVE or GRACE);
 * unlike the directory, a shop with no active card still shows. The number
 * is the one the shop chose to publish, never the one it registered with.
 * One response with an ETag, like the directory.
 */
class ShopContactController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        $statuses = array_filter(MerchantStatus::cases(), fn (MerchantStatus $status): bool => $status->appearsInDirectory());

        $data = Merchant::query()
            ->with(['businessType', 'governorate'])
            ->whereIn('status', $statuses)
            ->orderBy('business_name')
            ->orderBy('id')
            ->get()
            ->map(fn (Merchant $merchant): array => [
                ...(new MerchantSummaryResource($merchant))->resolve($request),
                'address' => $merchant->address,
                'contact_phone' => $merchant->contact_phone,
            ])
            ->all();

        $etag = sha1(json_encode($data));

        if (in_array('"'.$etag.'"', $request->getETags(), true)) {
            return response()->noContent(304)->setEtag($etag);
        }

        return response()->json(['data' => $data])->setEtag($etag);
    }
}
