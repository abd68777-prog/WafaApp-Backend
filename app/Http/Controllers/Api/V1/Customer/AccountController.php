<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\DeletionSource;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customer\CustomerAccountDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account deletion from inside the app, required by both stores. The app
 * shows what is deleted and what stays before calling this, and warns that
 * uncollected stamps and rewards are lost.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, CustomerAccountDeleter $deleter): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $deleter->delete($customer, DeletionSource::App);

        return response()->json(['message' => 'Account deleted.']);
    }
}
