<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Customer;
use App\Models\PolicyConsent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A new privacy policy version is announced in the app, and the customer's
 * agreement to it is recorded per version (privacy policy §14).
 */
class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_who_agreed_to_the_current_version_has_nothing_to_accept(): void
    {
        $customer = Customer::factory()->create();
        PolicyConsent::factory()->create(['customer_id' => $customer->id, 'policy_version' => '1.2']);

        $response = $this->withToken($this->customerToken($customer))->getJson('/api/v1/customer/auth/me');

        $response->assertOk()->assertJsonPath('data.policy', [
            'accepted_version' => '1.2',
            'current_version' => '1.2',
            'update_required' => false,
        ]);
    }

    public function test_a_new_policy_version_is_flagged_until_the_customer_accepts_it(): void
    {
        Setting::write('privacy_policy_version', '1.3');
        $customer = Customer::factory()->create();
        PolicyConsent::factory()->create(['customer_id' => $customer->id, 'policy_version' => '1.2']);

        $this->withToken($this->customerToken($customer))
            ->getJson('/api/v1/customer/auth/me')
            ->assertJsonPath('data.policy.update_required', true);

        $response = $this->withToken($this->customerToken($customer))
            ->postJson('/api/v1/customer/policy/accept', ['policy_version' => '1.3']);

        $response->assertOk()
            ->assertJsonPath('data.policy.accepted_version', '1.3')
            ->assertJsonPath('data.policy.update_required', false);

        $this->assertDatabaseHas('policy_consents', ['customer_id' => $customer->id, 'policy_version' => '1.3']);
    }

    public function test_accepting_a_version_that_is_no_longer_current_is_rejected(): void
    {
        Setting::write('privacy_policy_version', '1.3');
        $customer = Customer::factory()->create();

        $response = $this->withToken($this->customerToken($customer))
            ->postJson('/api/v1/customer/policy/accept', ['policy_version' => '1.2']);

        $response->assertUnprocessable()->assertJsonValidationErrors('policy_version');

        $this->assertDatabaseCount('policy_consents', 0);
    }

    private function customerToken(Customer $customer): string
    {
        return $customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
