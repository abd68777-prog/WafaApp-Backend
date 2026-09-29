<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\AcceptPolicyRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Setting;

/**
 * The privacy policy promises to tell customers in the app about any material
 * change (§14). Each agreement is kept per version, with its time.
 */
class PolicyController extends Controller
{
    public function store(AcceptPolicyRequest $request): CustomerResource
    {
        $version = $request->string('policy_version')->value();

        if ($version !== Setting::currentPolicyVersion()) {
            throw ApiException::of(ErrorCode::PolicyVersionOutdated, 'This is not the current privacy policy.', [
                'current_version' => Setting::currentPolicyVersion(),
            ]);
        }

        /** @var Customer $customer */
        $customer = $request->user();

        $customer->policyConsents()->firstOrCreate(
            ['policy_version' => $version],
            ['consented_at' => now()],
        );

        return new CustomerResource($customer);
    }
}
