<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ClientApp;
use App\Enums\DevicePlatform;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Push tokens follow whoever is signed in on the device, and signing out
 * stops that device's notifications.
 */
class DeviceTest extends TestCase
{
    use RefreshDatabase;

    private const FCM_TOKEN = 'fcm-token-of-the-phone';

    public function test_a_customer_registers_the_device_they_signed_in_on(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withToken($this->customerToken($customer))->postJson('/api/v1/customer/devices', [
            'token' => self::FCM_TOKEN,
            'platform' => 'ios',
        ]);

        $response->assertOk();

        $device = DeviceToken::query()->sole();
        $this->assertTrue($device->owner->is($customer));
        $this->assertSame(DevicePlatform::Ios, $device->platform);
        $this->assertSame(ClientApp::Customer, $device->app);
    }

    public function test_a_device_token_moves_to_whoever_signs_in_on_that_phone_next(): void
    {
        $previousOwner = Customer::factory()->create();
        DeviceToken::factory()->create(['owner_id' => $previousOwner->id, 'token' => self::FCM_TOKEN]);
        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);

        $response = $this->withToken($this->clerkToken('user_shop'))->postJson('/api/v1/merchant/devices', [
            'token' => self::FCM_TOKEN,
            'platform' => 'android',
        ]);

        $response->assertOk();

        $device = DeviceToken::query()->sole();
        $this->assertInstanceOf(Merchant::class, $device->owner);
        $this->assertSame(ClientApp::Merchant, $device->app);
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->withToken($this->customerToken($customer))->postJson('/api/v1/customer/devices', [
            'token' => self::FCM_TOKEN,
            'platform' => 'windows',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('platform');

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_customer_logout_forgets_only_this_device(): void
    {
        $customer = Customer::factory()->create();
        $thisPhone = DeviceToken::factory()->create(['owner_id' => $customer->id, 'token' => self::FCM_TOKEN]);
        $tablet = DeviceToken::factory()->create(['owner_id' => $customer->id]);

        $response = $this->withToken($this->customerToken($customer))->postJson('/api/v1/customer/auth/logout', [
            'device_token' => self::FCM_TOKEN,
        ]);

        $response->assertOk();

        $this->assertModelMissing($thisPhone);
        $this->assertModelExists($tablet);
    }

    public function test_customer_logout_on_all_devices_forgets_every_device(): void
    {
        $customer = Customer::factory()->create();
        DeviceToken::factory()->count(2)->create(['owner_id' => $customer->id]);

        $response = $this->withToken($this->customerToken($customer))->postJson('/api/v1/customer/auth/logout-all');

        $response->assertOk();

        $this->assertSame(0, $customer->deviceTokens()->count());
    }

    public function test_merchant_logout_forgets_this_device(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $device = DeviceToken::factory()->create([
            'owner_type' => $merchant->getMorphClass(),
            'owner_id' => $merchant->id,
            'token' => self::FCM_TOKEN,
            'app' => ClientApp::Merchant,
        ]);

        $response = $this->withToken($this->clerkToken('user_shop'))->postJson('/api/v1/merchant/auth/logout', [
            'device_token' => self::FCM_TOKEN,
        ]);

        $response->assertOk();

        $this->assertModelMissing($device);
    }

    public function test_merchant_logout_cannot_forget_another_accounts_device(): void
    {
        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $someoneElses = DeviceToken::factory()->create(['token' => self::FCM_TOKEN]);

        $this->withToken($this->clerkToken('user_shop'))
            ->postJson('/api/v1/merchant/auth/logout', ['device_token' => self::FCM_TOKEN])
            ->assertOk();

        $this->assertModelExists($someoneElses);
    }

    private function customerToken(Customer $customer): string
    {
        return $customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
