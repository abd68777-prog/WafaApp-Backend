<?php

namespace Tests\Feature\Database;

use App\Enums\CustomerStatus;
use App\Enums\MerchantStatus;
use App\Models\Customer;
use App\Models\CustomerCardProgress;
use App\Models\LoyaltyCard;
use App\Models\Merchant;
use App\Models\Reward;
use App\Models\StampLog;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stamp_log_factory_keeps_denormalized_owner_columns_consistent(): void
    {
        $stampLog = StampLog::factory()->create();

        $progress = $stampLog->cardProgress;
        $this->assertSame($progress->customer_id, $stampLog->customer_id);
        $this->assertSame($progress->loyalty_card_id, $stampLog->loyalty_card_id);
        $this->assertSame($progress->merchant_id, $stampLog->merchant_id);
        $this->assertSame($progress->loyaltyCard->merchant_id, $progress->merchant_id);
    }

    public function test_rejects_enrolling_the_same_customer_twice_on_one_card(): void
    {
        $customer = Customer::factory()->create();
        $card = LoyaltyCard::factory()->create();
        CustomerCardProgress::factory()->for($customer)->for($card)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        CustomerCardProgress::factory()->for($customer)->for($card)->create();
    }

    public function test_rejects_a_duplicate_offline_stamp_sync(): void
    {
        $progress = CustomerCardProgress::factory()->create();
        StampLog::factory()->for($progress, 'cardProgress')->create(['client_uuid' => '9b2f6f0e-2f4c-4f4a-9a34-2d0f4a7e8c11']);

        $this->expectException(UniqueConstraintViolationException::class);

        StampLog::factory()->for($progress, 'cardProgress')->create(['client_uuid' => '9b2f6f0e-2f4c-4f4a-9a34-2d0f4a7e8c11']);
    }

    public function test_rejects_a_second_customer_with_the_same_phone_number(): void
    {
        Customer::factory()->pending()->create(['phone' => '+963911111111']);

        $this->expectException(UniqueConstraintViolationException::class);

        Customer::factory()->create(['phone' => '+963911111111']);
    }

    public function test_rejects_a_second_birthday_gift_for_the_same_merchant_in_one_year(): void
    {
        $customer = Customer::factory()->create();
        $merchant = Merchant::factory()->create();
        Reward::factory()->birthday()->for($customer)->for($merchant)->create(['period_key' => '2026']);

        $this->expectException(UniqueConstraintViolationException::class);

        Reward::factory()->birthday()->for($customer)->for($merchant)->create(['period_key' => '2026']);
    }

    public function test_allows_repeated_card_completion_rewards_for_the_same_customer(): void
    {
        $progress = CustomerCardProgress::factory()->create();

        Reward::factory()->redeemed()->for($progress, 'cardProgress')->create();
        Reward::factory()->for($progress, 'cardProgress')->create();

        $this->assertSame(2, $progress->rewards()->count());
    }

    public function test_pending_customer_is_stored_without_a_qr_token(): void
    {
        $customer = Customer::factory()->pending()->create();

        $storedCustomer = $customer->fresh();
        $this->assertNull($storedCustomer->qr_token);
        $this->assertSame(CustomerStatus::Pending, $storedCustomer->status);
    }

    public function test_force_deleting_a_card_removes_its_customer_progress(): void
    {
        $progress = CustomerCardProgress::factory()->create();

        $progress->loyaltyCard->forceDelete();

        $this->assertModelMissing($progress);
    }

    public function test_soft_deleting_a_card_keeps_its_customer_progress(): void
    {
        $progress = CustomerCardProgress::factory()->create();

        $progress->loyaltyCard->delete();

        $this->assertModelExists($progress);
        $this->assertSoftDeleted('loyalty_cards', ['id' => $progress->loyalty_card_id]);
    }

    public function test_merchant_cannot_mass_assign_their_own_status(): void
    {
        $merchant = Merchant::factory()->pendingReview()->create();

        $this->expectException(MassAssignmentException::class);

        $merchant->fill(['status' => MerchantStatus::Active->value]);
    }

    public function test_tokens_store_the_morph_alias_instead_of_the_class_name(): void
    {
        $customer = Customer::factory()->create();

        $customer->createToken('customer-app');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => 'customer',
            'tokenable_id' => $customer->id,
        ]);
    }
}
