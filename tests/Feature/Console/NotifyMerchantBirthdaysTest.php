<?php

namespace Tests\Feature\Console;

use App\Enums\MerchantStatus;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Each morning a shop learns how many of its customers have their birthday,
 * once a day, and only while its subscription allows greetings.
 */
class NotifyMerchantBirthdaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 09:00 in Damascus on 30 September.
        $this->travelTo('2026-09-30 06:00:00');
    }

    public function test_a_shop_hears_how_many_of_its_customers_have_their_birthday(): void
    {
        $shop = Merchant::factory()->create();
        $this->customerOf($shop, '1998-09-30');
        $this->customerOf($shop, '2001-09-30');
        $this->customerOf($shop, '1998-10-01');
        $this->customerOf(Merchant::factory()->create(), '1990-09-30');

        $this->artisan('birthdays:notify-merchants')->assertSuccessful();

        $entry = $shop->notifications()->sole();
        $this->assertSame('birthdays_today', $entry->type);
        $this->assertSame('اليوم عيد ميلاد 2 من زبائنك. هنّئهم من التطبيق.', $entry->data['body']);
        $this->assertSame(['count' => 2], $entry->data['data']);
    }

    public function test_shops_without_birthdays_or_that_cannot_greet_hear_nothing(): void
    {
        Notification::fake();
        $quiet = Merchant::factory()->create();
        $this->customerOf($quiet, '1998-10-01');
        $expired = Merchant::factory()->create(['status' => MerchantStatus::Expired]);
        $this->customerOf($expired, '1998-09-30');

        $this->artisan('birthdays:notify-merchants')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_running_again_the_same_day_tells_no_one_twice(): void
    {
        $shop = Merchant::factory()->create();
        $this->customerOf($shop, '1998-09-30');

        $this->artisan('birthdays:notify-merchants');
        $this->artisan('birthdays:notify-merchants');

        $this->assertSame(1, $shop->notifications()->count());
        $this->assertSame('اليوم عيد ميلاد أحد زبائنك. هنّئه من التطبيق.', $shop->notifications()->sole()->data['body']);
    }

    public function test_it_is_scheduled_every_morning_in_damascus(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->sole(fn (Event $event): bool => str_contains($event->command, 'birthdays:notify-merchants'));

        $this->assertSame('0 9 * * *', $event->expression);
        $this->assertSame('Asia/Damascus', $event->timezone);
    }

    private function customerOf(Merchant $merchant, string $birthdate): Customer
    {
        $customer = Customer::factory()->create(['birthdate' => $birthdate]);
        CardCycle::factory()->for(Card::factory()->for($merchant))->for($customer)->create();

        return $customer;
    }
}
