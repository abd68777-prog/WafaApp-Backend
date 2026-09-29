<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CompleteProfileRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * The name and birthdate, entered once right after the first sign-in. Neither
 * can be changed from the app afterwards; support corrects a birthdate.
 */
class ProfileController extends Controller
{
    /**
     * Nobody under 13 may hold an account (privacy policy §1.2).
     */
    public const MINIMUM_AGE = 13;

    public function store(CompleteProfileRequest $request): CustomerResource
    {
        /** @var Customer $customer */
        $customer = $request->user();

        if ($customer->hasCompleteProfile()) {
            throw ApiException::of(ErrorCode::ProfileAlreadyCompleted, 'The profile was already completed.');
        }

        $birthdate = Carbon::createFromFormat('Y-m-d', $request->string('birthdate')->value())->startOfDay();

        if ($birthdate->diffInYears(today()) < self::MINIMUM_AGE) {
            throw ApiException::of(ErrorCode::UnderAge, 'Customers must be at least '.self::MINIMUM_AGE.' years old.', [
                'min_age' => self::MINIMUM_AGE,
            ]);
        }

        $customer->update([
            'name' => $request->string('name')->trim()->value(),
            'birthdate' => $birthdate->toDateString(),
        ]);

        return new CustomerResource($customer);
    }
}
