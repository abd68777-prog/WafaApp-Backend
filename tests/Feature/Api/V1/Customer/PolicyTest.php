<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Customer;
use App\Models\PolicyConsent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A new privacy policy version is announced in the app, and the customer's
 * agreement to it is recorded per version (privacy policy §14). Until then the
 * app is limited to the screens that let the customer agree or leave.
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_account_shows_which_version_the_customer_agreed_to(): void
    {
        $customer = Customer::factory()->create();
        PolicyConsent::factory()->create(['customer_id' => $customer->id, 'policy_version' => '1.2']);

        $response = $this->withToken($this->customerToken($customer))->getJson('/api/v1/customer/me');

        $response->assertOk()->assertJsonPath('data.consented_policy_version', '1.2');
    }

    public function test_a_new_version_blocks_the_app_until_the_customer_agrees_to_it(): void
    {
        Setting::write('privacy_policy_version', '1.3');
        $customer = Customer::factory()->create();
        PolicyConsent::factory()->create(['customer_id' => $customer->id, 'policy_version' => '1.2']);
        $token = $this->customerToken($customer);

        $this->withToken($token)->getJson('/api/v1/customer/me/qr')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'POLICY_CONSENT_REQUIRED')
            ->assertJsonPath('error.details.current_version', '1.3');

        $this->withToken($token)->postJson('/api/v1/customer/me/policy-consents', ['policy_version' => '1.3'])
            ->assertOk()
            ->assertJsonPath('data.consented_policy_version', '1.3');

        $this->withToken($token)->getJson('/api/v1/customer/me/qr')->assertOk();
        $this->assertDatabaseHas('policy_consents', ['customer_id' => $customer->id, 'policy_version' => '1.3']);
    }

    public function test_agreeing_to_a_version_that_is_no_longer_current_is_refused(): void
    {
        Setting::write('privacy_policy_version', '1.3');
        $customer = Customer::factory()->create();

        $response = $this->withToken($this->customerToken($customer))
            ->postJson('/api/v1/customer/me/policy-consents', ['policy_version' => '1.2']);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'POLICY_VERSION_OUTDATED')
            ->assertJsonPath('error.details.current_version', '1.3');

        $this->assertDatabaseCount('policy_consents', 0);
    }

    private function customerToken(Customer $customer): string
    {
        return $customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
