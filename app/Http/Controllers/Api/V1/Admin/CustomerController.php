<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateCustomerBirthdateRequest;
use App\Http\Resources\AdminCustomerResource;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Rules\SyrianPhone;
use App\Services\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * The customers (requirements §5.4): found by their full number only, never
 * browsed, and shown masked. Revealing the number and correcting a birthdate
 * are both written to the audit trail. There is no blocking: a customer
 * cannot harm anyone — merchants add the stamps.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * One customer or none, as a list so the screen renders both alike.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->merge(['phone' => PhoneNumber::normalize((string) $request->query('phone', '')) ?? $request->query('phone')]);
        $validated = $request->validate([
            'phone' => ['required', 'string', new SyrianPhone],
        ]);

        return AdminCustomerResource::collection(
            Customer::query()->where('phone', $validated['phone'])->get()
        );
    }

    public function show(Customer $customer): AdminCustomerResource
    {
        return new AdminCustomerResource($this->withCycles($customer));
    }

    public function update(UpdateCustomerBirthdateRequest $request, Customer $customer): AdminCustomerResource
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $birthdate = Carbon::createFromFormat('Y-m-d', $request->string('birthdate')->value())->startOfDay();

        if ($birthdate->diffInYears(today()) < ProfileController::MINIMUM_AGE) {
            throw ApiException::of(ErrorCode::UnderAge, 'Customers must be at least '.ProfileController::MINIMUM_AGE.' years old.', [
                'min_age' => ProfileController::MINIMUM_AGE,
            ]);
        }

        $before = $customer->birthdate?->toDateString();

        if ($before !== $birthdate->toDateString()) {
            $customer->update(['birthdate' => $birthdate->toDateString()]);

            $this->audit->record($admin, 'customer.birthdate_updated', $customer, ['birthdate' => $before], [
                'birthdate' => $birthdate->toDateString(),
                'reason' => $request->string('reason')->trim()->value(),
            ], $request->ip());
        }

        return new AdminCustomerResource($this->withCycles($customer->refresh()));
    }

    /**
     * Every reveal is recorded — who looked at which number, and when.
     */
    public function revealPhone(Request $request, Customer $customer): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $this->audit->record($admin, 'customer.phone_revealed', $customer, ipAddress: $request->ip());

        return response()->json(['data' => ['phone' => $customer->phone]]);
    }

    private function withCycles(Customer $customer): Customer
    {
        return $customer->load(['cardCycles' => fn ($query) => $query
            ->with(['card:id,name,status,stamps_required', 'merchant' => fn ($merchant) => $merchant->withTrashed()->select('id', 'business_name')])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')]);
    }
}
