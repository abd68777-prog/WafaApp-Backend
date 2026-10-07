<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Enums\MerchantStatus;
use App\Models\BirthdayGreeting;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantMute;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Today's birthdays and the merchant's greeting (contract §5.6, with the
 * message and gift the frontend asked for): one per customer per day, sent
 * even to customers who muted offers, and never counted as a campaign.
 */
class BirthdayTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        // 10:00 in Damascus on 30 September.
        $this->travelTo('2026-09-30 07:00:00');
        $this->merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER, 'business_name' => 'كافيه الياسمين']);
        $this->card = Card::factory()->for($this->merchant)->create();
    }

    public function test_the_list_shows_todays_birthdays_among_the_shops_customers_without_the_year(): void
    {
        $sara = $this->customerOfTheShop(['name' => 'سارة', 'birthdate' => '1998-09-30']);
        $this->customerOfTheShop(['name' => 'عمر', 'birthdate' => '1998-10-01']);
        Customer::factory()->create(['birthdate' => '1990-09-30']);
        BirthdayGreeting::factory()->create(['merchant_id' => $this->merchant->id, 'customer_id' => $sara->id]);

        $response = $this->withPin()->getJson('/api/v1/merchant/customers/birthdays-today');

        $response->assertOk()->assertExactJson(['data' => [
            ['id' => $sara->id, 'name' => 'سارة', 'birthday' => '09-30', 'greeted_today' => true],
        ]]);
    }

    public function test_a_greeting_reaches_the_customer_with_the_gift_even_when_offers_are_muted(): void
    {
        $customer = $this->customerOfTheShop(['birthdate' => '1998-09-30', 'campaigns_muted' => true]);
        MerchantMute::factory()->create(['customer_id' => $customer->id, 'merchant_id' => $this->merchant->id]);

        $response = $this->greet($customer, ['message' => 'كل عام وأنت بخير!', 'gift' => 'قهوة مجانية']);

        $response->assertCreated()->assertExactJson(['data' => [
            'customer_id' => $customer->id,
            'greeted_on' => '2026-09-30',
            'message' => 'كل عام وأنت بخير!',
            'gift' => 'قهوة مجانية',
        ]]);

        $notification = $customer->notifications()->sole();
        $this->assertSame('birthday_greeting', $notification->type);
        $this->assertSame('عيد ميلاد سعيد من كافيه الياسمين', $notification->data['title']);
        $this->assertSame("كل عام وأنت بخير!\nهديتك: قهوة مجانية", $notification->data['body']);
        $this->assertSame(0, $this->merchant->campaigns()->count());
    }

    public function test_a_second_tap_the_same_day_returns_the_first_greeting_and_sends_nothing(): void
    {
        $customer = $this->customerOfTheShop(['birthdate' => '1998-09-30']);
        $this->greet($customer, ['message' => 'كل عام وأنت بخير!'])->assertCreated();

        $this->greet($customer, ['message' => 'رسالة تانية'])
            ->assertOk()
            ->assertJsonPath('data.message', 'كل عام وأنت بخير!')
            ->assertJsonPath('data.gift', null);

        $this->assertSame(1, $customer->notifications()->count());
    }

    public function test_a_greeting_is_refused_when_it_is_not_the_customers_birthday(): void
    {
        $customer = $this->customerOfTheShop(['birthdate' => '1998-10-01']);

        $this->greet($customer, ['message' => 'كل عام وأنت بخير!'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BIRTHDAY_NOT_TODAY');

        $this->assertDatabaseCount('birthday_greetings', 0);
    }

    public function test_links_are_refused_in_the_message_and_the_gift(): void
    {
        $customer = $this->customerOfTheShop(['birthdate' => '1998-09-30']);

        $this->greet($customer, ['message' => 'هديتك على wafa-offers.com'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CAMPAIGN_CONTAINS_LINK')
            ->assertJsonPath('error.details.field', 'message');

        $this->greet($customer, ['message' => 'كل عام وأنت بخير!', 'gift' => 'https://bit.ly/x'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.field', 'gift');

        $this->assertDatabaseCount('birthday_greetings', 0);
    }

    public function test_the_message_is_required_and_limited_in_length(): void
    {
        $customer = $this->customerOfTheShop(['birthdate' => '1998-09-30']);

        $this->greet($customer, ['message' => str_repeat('ا', 301), 'gift' => str_repeat('ه', 61)])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['message' => ['max'], 'gift' => ['max']]);

        $this->greet($customer, [])->assertUnprocessable()->assertJsonPath('error.details.fields.message', ['required']);
    }

    public function test_an_expired_shop_cannot_send_greetings(): void
    {
        $this->merchant->forceFill(['status' => MerchantStatus::Expired])->save();
        $customer = $this->customerOfTheShop(['birthdate' => '1998-09-30']);

        $this->greet($customer, ['message' => 'كل عام وأنت بخير!'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'MERCHANT_STATUS_BLOCKS_ACTION')
            ->assertJsonPath('error.details', ['status' => 'EXPIRED', 'action' => 'campaigns']);
    }

    public function test_only_the_shops_own_customers_can_be_greeted(): void
    {
        $stranger = Customer::factory()->create(['birthdate' => '1998-09-30']);

        $this->greet($stranger, ['message' => 'كل عام وأنت بخير!'])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_birthdays_are_behind_the_pin(): void
    {
        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->getJson('/api/v1/merchant/customers/birthdays-today')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_those_born_on_29_february_celebrate_on_the_28th_in_other_years(): void
    {
        $this->travelTo('2027-02-28 07:00:00');
        $leapling = $this->customerOfTheShop(['birthdate' => '2000-02-29']);

        $this->withPin()->getJson('/api/v1/merchant/customers/birthdays-today')->assertJsonPath('data.0.id', $leapling->id);
        $this->greet($leapling, ['message' => 'كل عام وأنت بخير!'])->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customerOfTheShop(array $attributes): Customer
    {
        $customer = Customer::factory()->create($attributes);
        CardCycle::factory()->for($this->card)->for($customer)->redeemed()->create();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function greet(Customer $customer, array $body): TestResponse
    {
        return $this->withPin()->postJson("/api/v1/merchant/customers/{$customer->id}/birthday-greeting", $body);
    }

    private function withPin(): self
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token']);
    }
}
