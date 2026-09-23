<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\AcceptPolicyRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;

/**
 * The privacy policy promises to tell customers in the app about any material
 * change (§14). Each agreement is kept per version, with its time.
 */
class PolicyController extends Controller
{
    public function accept(AcceptPolicyRequest $request): CustomerResource
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $customer->policyConsents()->firstOrCreate(
            ['policy_version' => $request->string('policy_version')->value()],
            ['consented_at' => now()],
        );

        return new CustomerResource($customer);
    }
}
