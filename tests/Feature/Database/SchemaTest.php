<?php

namespace Tests\Feature\Database;

use App\Enums\CardCycleStatus;
use App\Models\BirthdayGreeting;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Stamp;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guarantees the database itself makes, whatever the application does.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_have_only_one_open_cycle_per_card(): void
    {
        $cycle = CardCycle::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        CardCycle::factory()->create([
            'card_id' => $cycle->card_id,
            'customer_id' => $cycle->customer_id,
            'merchant_id' => $cycle->merchant_id,
        ]);
    }

    public function test_a_new_cycle_opens_once_the_previous_one_was_redeemed(): void
    {
        $cycle = CardCycle::factory()->redeemed()->create();

        $next = CardCycle::factory()->create([
            'card_id' => $cycle->card_id,
            'customer_id' => $cycle->customer_id,
            'merchant_id' => $cycle->merchant_id,
        ]);

        $this->assertSame(CardCycleStatus::Collecting, $next->status);
        $this->assertSame(2, CardCycle::query()->count());
    }

    public function test_a_repeated_stamp_request_cannot_add_two_stamps(): void
    {
        $cycle = CardCycle::factory()->create();
        Stamp::factory()->for($cycle)->create(['client_uuid' => '9b2f6f0e-2f4c-4f4a-9a34-2d0f4a7e8c11']);

        $this->expectException(UniqueConstraintViolationException::class);

        Stamp::factory()->for($cycle)->create(['client_uuid' => '9b2f6f0e-2f4c-4f4a-9a34-2d0f4a7e8c11']);
    }

    public function test_a_merchant_can_have_only_one_payment_awaiting_review(): void
    {
        $payment = Payment::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Payment::factory()->create([
            'merchant_id' => $payment->merchant_id,
            'package_id' => $payment->package_id,
        ]);
    }

    public function test_a_new_payment_is_allowed_once_the_previous_one_was_decided(): void
    {
        $payment = Payment::factory()->approved()->create();

        Payment::factory()->create([
            'merchant_id' => $payment->merchant_id,
            'package_id' => $payment->package_id,
        ]);

        $this->assertSame(2, Payment::query()->count());
    }

    public function test_a_phone_number_belongs_to_one_customer_only(): void
    {
        Customer::factory()->pending()->create(['phone' => '+963911111111']);

        $this->expectException(UniqueConstraintViolationException::class);

        Customer::factory()->create(['phone' => '+963911111111']);
    }

    public function test_a_shop_cannot_greet_the_same_customer_twice_in_one_day(): void
    {
        $greeting = BirthdayGreeting::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        BirthdayGreeting::factory()->create([
            'merchant_id' => $greeting->merchant_id,
            'customer_id' => $greeting->customer_id,
            'greeted_on' => $greeting->greeted_on,
        ]);
    }

    public function test_a_phone_only_customer_is_stored_without_a_qr_secret(): void
    {
        $customer = Customer::factory()->pending()->create();

        $this->assertNull($customer->qr_secret);
        $this->assertTrue($customer->isPending());
    }

    public function test_deleting_a_card_removes_its_cycles_and_stamps(): void
    {
        $stamp = Stamp::factory()->create();
        $card = Card::find($stamp->card_id);

        $card->delete();

        $this->assertModelMissing($stamp);
        $this->assertDatabaseCount('card_cycles', 0);
    }

    public function test_a_merchant_cannot_mass_assign_their_own_status(): void
    {
        $merchant = Merchant::factory()->awaitingPackage()->create();

        $this->expectException(MassAssignmentException::class);

        $merchant->fill(['status' => 'ACTIVE']);
    }

    public function test_customer_tokens_store_the_morph_alias_instead_of_the_class_name(): void
    {
        $customer = Customer::factory()->create();

        $customer->createToken('customer-app', ['customer']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => 'customer',
            'tokenable_id' => $customer->id,
        ]);
    }
}
