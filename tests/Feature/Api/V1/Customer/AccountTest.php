<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use App\Models\BirthdayGreeting;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\DeletionRequest;
use App\Models\DeviceToken;
use App\Models\MerchantMute;
use App\Models\OtpCode;
use App\Models\PolicyConsent;
use App\Models\Stamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting an account from the app, as the privacy policy describes it (§10):
 * personal data goes at once, stamps stay behind without anyone attached.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+963947123456';

    public function test_deleting_the_account_removes_every_personal_detail_and_keeps_the_stamps_anonymous(): void
    {
        $customer = Customer::factory()->create(['phone' => self::PHONE]);
        $card = Card::factory()->create();
        $cycle = CardCycle::factory()->create([
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
            'stamps_count' => 3,
        ]);
        $stamps = Stamp::factory()->count(3)->create([
            'card_cycle_id' => $cycle->id,
            'card_id' => $card->id,
            'customer_id' => $customer->id,
            'merchant_id' => $card->merchant_id,
        ]);
        PolicyConsent::factory()->create(['customer_id' => $customer->id]);
        MerchantMute::factory()->create(['customer_id' => $customer->id, 'merchant_id' => $card->merchant_id]);
        BirthdayGreeting::factory()->create(['customer_id' => $customer->id, 'merchant_id' => $card->merchant_id]);
        DeviceToken::factory()->create(['owner_id' => $customer->id]);
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->withToken($this->customerToken($customer))->deleteJson('/api/v1/customer/account');

        $response->assertOk();

        $deleted = Customer::withTrashed()->find($customer->id);
        $this->assertSoftDeleted($deleted);
        $this->assertNull($deleted->phone);
        $this->assertNull($deleted->name);
        $this->assertNull($deleted->birthdate);
        $this->assertNull($deleted->qr_secret);

        $this->assertSame(0, $deleted->tokens()->count());
        $this->assertDatabaseCount('device_tokens', 0);
        $this->assertDatabaseCount('policy_consents', 0);
        $this->assertDatabaseCount('merchant_mutes', 0);
        $this->assertDatabaseCount('birthday_greetings', 0);
        $this->assertDatabaseCount('otp_codes', 0);

        // Merchant statistics still count these visits.
        $this->assertModelExists($cycle);
        $stamps->each(fn (Stamp $stamp) => $this->assertModelExists($stamp));

        $request = DeletionRequest::query()->sole();
        $this->assertSame(DeletionSubjectType::Customer, $request->subject_type);
        $this->assertSame($customer->id, $request->subject_id);
        $this->assertSame(DeletionSource::App, $request->source);
        $this->assertNotNull($request->executed_at);
    }

    public function test_the_deleted_accounts_token_no_longer_works(): void
    {
        $customer = Customer::factory()->create();
        $token = $this->customerToken($customer);

        $this->withToken($token)->deleteJson('/api/v1/customer/account')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/customer/auth/me')->assertUnauthorized();
    }

    public function test_the_same_number_can_sign_up_again_as_a_new_account_without_the_old_stamps(): void
    {
        $customer = Customer::factory()->create(['phone' => self::PHONE]);
        CardCycle::factory()->create(['customer_id' => $customer->id, 'stamps_count' => 5]);
        $this->withToken($this->customerToken($customer))->deleteJson('/api/v1/customer/account')->assertOk();
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $response = $this->postJson('/api/v1/customer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => '123456',
            'name' => 'سارة',
            'birthdate' => '1998-05-20',
            'policy_version' => '1.2',
        ]);

        $response->assertOk()
            ->assertJsonPath('is_new_customer', true)
            ->assertJsonPath('stamps_waiting', []);

        $this->assertNotSame($customer->id, $response->json('data.id'));
    }

    public function test_deleting_requires_a_customer_token(): void
    {
        $this->deleteJson('/api/v1/customer/account')->assertUnauthorized();
    }

    private function customerToken(Customer $customer): string
    {
        return $customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
