<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Switching one shop's offers off and on (contract §4.4). It silences only
 * that shop's campaigns: stamps and rewards are still notified, and other
 * shops are not punished for one annoying merchant.
 */
class MerchantMuteController extends Controller
{
    public function update(Request $request, int $merchant): Response
    {
        $this->customer($request)->merchantMutes()->firstOrCreate(['merchant_id' => $this->registered($merchant)->id]);

        return response()->noContent();
    }

    public function destroy(Request $request, int $merchant): Response
    {
        $this->customer($request)->merchantMutes()->where('merchant_id', $this->registered($merchant)->id)->delete();

        return response()->noContent();
    }

    /**
     * A shop customers can see: it finished registration and was not deleted.
     */
    private function registered(int $merchantId): Merchant
    {
        return Merchant::query()->whereNotNull('status')->findOrFail($merchantId);
    }

    private function customer(Request $request): Customer
    {
        return $request->user();
    }
}
