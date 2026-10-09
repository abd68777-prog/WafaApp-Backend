<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\CardCycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantCustomerResource;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Stamp;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The shop's customers (contract §5.6): everyone with a cycle on one of its
 * cards, pending numbers included, most recently active here first. Only the
 * activity at this shop orders the list — what a customer does elsewhere is
 * none of its business. Search is by name only; the number stays masked.
 */
class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'min:2', 'max:50'],
        ]);

        $activity = CardCycle::query()
            ->selectRaw('customer_id, max(updated_at) as last_activity_at')
            ->where('merchant_id', $merchant->id)
            ->groupBy('customer_id');

        $customers = Customer::query()
            ->joinSub($activity, 'shop_activity', 'shop_activity.customer_id', '=', 'customers.id')
            ->select('customers.*', 'shop_activity.last_activity_at')
            ->when(isset($validated['q']), fn ($query) => $query->where('customers.name', 'like', '%'.addcslashes($validated['q'], '%_\\').'%'))
            ->orderByDesc('shop_activity.last_activity_at')
            ->orderByDesc('customers.id');

        return CursorPage::respond($request, $customers, MerchantCustomerResource::class, [], function (Collection $page) use ($merchant): void {
            $this->loadShopDetails($page, $merchant);
        });
    }

    /**
     * The open cycles and the last stamp here, for the whole page at once.
     *
     * @param  Collection<int, Customer>  $customers
     */
    private function loadShopDetails(Collection $customers, Merchant $merchant): void
    {
        $ids = $customers->pluck('id')->all();

        $cycles = CardCycle::query()
            ->with('card:id,name,stamps_required')
            ->where('merchant_id', $merchant->id)
            ->whereIn('customer_id', $ids)
            ->whereIn('status', [CardCycleStatus::Collecting, CardCycleStatus::RewardReady])
            ->orderBy('card_id')
            ->get()
            ->groupBy('customer_id');

        $lastStamps = Stamp::query()
            ->selectRaw('customer_id, max(stamped_at) as last_stamp_at')
            ->where('merchant_id', $merchant->id)
            ->whereIn('customer_id', $ids)
            ->whereNull('cancelled_at')
            ->groupBy('customer_id')
            ->pluck('last_stamp_at', 'customer_id');

        foreach ($customers as $customer) {
            $customer->setRelation('openCycles', $cycles->get($customer->id, collect()));
            $customer->setAttribute('shop_last_stamp_at', $lastStamps[$customer->id] ?? null);
        }
    }
}
