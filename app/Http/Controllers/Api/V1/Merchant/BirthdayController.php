<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\SendBirthdayGreetingRequest;
use App\Models\BirthdayGreeting;
use App\Models\Customer;
use App\Models\Merchant;
use App\Notifications\BirthdayGreetingReceived;
use App\Services\Merchant\Birthdays;
use App\Support\LinkDetector;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Birthday greetings (contract §5.6). Manual only: the merchant sees today's
 * birthdays and decides whether to greet each customer. A greeting does not
 * count against the weekly campaign limit, and a customer gets at most one
 * per shop per day however many times send is tapped.
 */
class BirthdayController extends Controller
{
    /**
     * Registered customers of the shop — anyone with a cycle on one of its
     * cards, even a redeemed one — whose birthday is today in Damascus. The
     * merchant never sees the year of birth.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();
        $today = Birthdays::today();

        $customers = Birthdays::on($today, Birthdays::customersOf($merchant))->orderBy('name')->get();

        $greeted = BirthdayGreeting::query()
            ->where('merchant_id', $merchant->id)
            ->whereDate('greeted_on', $today->toDateString())
            ->pluck('customer_id')
            ->flip();

        return response()->json([
            'data' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'birthday' => $customer->birthdate->format('m-d'),
                'greeted_today' => $greeted->has($customer->id),
            ])->all(),
        ]);
    }

    public function store(SendBirthdayGreetingRequest $request, int $customer): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        if (! $merchant->status->canSendCampaigns()) {
            throw ApiException::of(ErrorCode::MerchantStatusBlocksAction, 'The subscription does not allow greetings now.', [
                'status' => $merchant->status->value,
                'action' => 'campaigns',
            ]);
        }

        $customer = Birthdays::customersOf($merchant)->whereKey($customer)->first()
            ?? throw ApiException::of(ErrorCode::NotFound, 'This customer is not a customer of the shop.');

        foreach (['message', 'gift'] as $field) {
            if (LinkDetector::containsLink((string) $request->input($field))) {
                throw ApiException::of(ErrorCode::CampaignContainsLink, 'Links are not allowed in greetings.', ['field' => $field]);
            }
        }

        $today = Birthdays::today();

        if ($existing = $this->greetingOn($merchant, $customer, $today)) {
            return $this->greetingResponse($existing, 200);
        }

        if (! Birthdays::on($today, Customer::query()->whereKey($customer->id))->exists()) {
            throw ApiException::of(ErrorCode::BirthdayNotToday, 'It is not this customer’s birthday today.');
        }

        try {
            $greeting = BirthdayGreeting::query()->create([
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'greeted_on' => $today->toDateString(),
                'message' => $request->string('message')->trim()->value(),
                'gift' => $request->filled('gift') ? $request->string('gift')->trim()->value() : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two taps raced each other: the first one greeted.
            return $this->greetingResponse($this->greetingOn($merchant, $customer, $today), 200);
        }

        $customer->notify(new BirthdayGreetingReceived($merchant, $greeting));

        return $this->greetingResponse($greeting, 201);
    }

    private function greetingOn(Merchant $merchant, Customer $customer, CarbonImmutable $day): ?BirthdayGreeting
    {
        return BirthdayGreeting::query()
            ->where('merchant_id', $merchant->id)
            ->where('customer_id', $customer->id)
            ->whereDate('greeted_on', $day->toDateString())
            ->first();
    }

    private function greetingResponse(BirthdayGreeting $greeting, int $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'customer_id' => $greeting->customer_id,
                'greeted_on' => $greeting->greeted_on->toDateString(),
                'message' => $greeting->message,
                'gift' => $greeting->gift,
            ],
        ], $status);
    }
}
