<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\DeletionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\UpdateMeRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Customer\CustomerAccountDeleter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The customer's own account (contract §4.2).
 */
class MeController extends Controller
{
    /**
     * Works even while the profile is incomplete or the consent outdated: it
     * is what tells the app which screen to show.
     */
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }

    public function update(UpdateMeRequest $request): CustomerResource
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $customer->update(['campaigns_muted' => $request->boolean('campaigns_muted')]);

        return new CustomerResource($customer);
    }

    /**
     * Account deletion from inside the app, required by both stores. The app
     * shows what is deleted and what stays before calling this, and warns that
     * uncollected stamps and rewards are lost.
     */
    public function destroy(Request $request, CustomerAccountDeleter $deleter): Response
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $deleter->delete($customer, DeletionSource::App);

        return response()->noContent();
    }
}
