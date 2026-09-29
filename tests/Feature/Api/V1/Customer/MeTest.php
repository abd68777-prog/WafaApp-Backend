<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer's own account: the profile entered once after the first
 * sign-in, the one editable setting, and the QR secret (contract §4.2).
 */
class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_account_is_readable_before_the_profile_is_complete(): void
    {
        $customer = Customer::factory()->withoutProfile()->consented()->create();

        $response = $this->withToken($this->customerToken($customer))->getJson('/api/v1/customer/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.profile_complete', false)
            ->assertJsonMissingPath('data.qr_secret');
    }

    public function test_the_rest_of_the_app_waits_for_the_profile(): void
    {
        $customer = Customer::factory()->withoutProfile()->consented()->create();

        $response = $this->withToken($this->customerToken($customer))->getJson('/api/v1/customer/me/qr');

        $response->assertForbidden()->assertJsonPath('error.code', 'PROFILE_INCOMPLETE');
    }

    public function test_the_profile_is_completed_once(): void
    {
        $customer = Customer::factory()->withoutProfile()->consented()->create();
        $token = $this->customerToken($customer);

        $this->withToken($token)
            ->postJson('/api/v1/customer/me/profile', ['name' => 'سارة', 'birthdate' => '1998-05-20'])
            ->assertOk()
            ->assertJsonPath('data.name', 'سارة')
            ->assertJsonPath('data.birthdate', '1998-05-20')
            ->assertJsonPath('data.profile_complete', true);

        $this->withToken($token)
            ->postJson('/api/v1/customer/me/profile', ['name' => 'غير سارة', 'birthdate' => '1990-01-01'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'PROFILE_ALREADY_COMPLETED');

        $this->assertSame('سارة', $customer->fresh()->name);
    }

    public function test_someone_under_thirteen_cannot_complete_a_profile(): void
    {
        $customer = Customer::factory()->withoutProfile()->consented()->create();

        $response = $this->withToken($this->customerToken($customer))->postJson('/api/v1/customer/me/profile', [
            'name' => 'طفل',
            'birthdate' => now()->subYears(12)->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'UNDER_AGE')
            ->assertJsonPath('error.details.min_age', 13);

        $this->assertNull($customer->fresh()->name);
    }

    public function test_the_only_editable_setting_is_muting_every_merchant(): void
    {
        $customer = Customer::factory()->consented()->create(['name' => 'سارة']);

        $response = $this->withToken($this->customerToken($customer))->patchJson('/api/v1/customer/me', [
            'campaigns_muted' => true,
            'name' => 'غير سارة',
        ]);

        $response->assertOk()->assertJsonPath('data.campaigns_muted', true)->assertJsonPath('data.name', 'سارة');
    }

    public function test_the_qr_secret_describes_how_the_app_generates_codes(): void
    {
        $customer = Customer::factory()->consented()->create();

        $response = $this->withToken($this->customerToken($customer))->getJson('/api/v1/customer/me/qr');

        $response->assertOk()->assertExactJson(['data' => [
            'qr_id' => $customer->qr_id,
            'secret' => $customer->qr_secret,
            'algorithm' => 'SHA256',
            'digits' => 8,
            'period_seconds' => 60,
        ]]);
    }

    public function test_the_config_is_public(): void
    {
        $response = $this->getJson('/api/v1/customer/config');

        $response->assertOk()
            ->assertJsonPath('data.qr_period_seconds', 60)
            ->assertJsonPath('data.privacy_policy_version', '1.2')
            ->assertJsonStructure(['data' => ['privacy_policy_url', 'customer_terms_url', 'governorates', 'business_types']]);
    }

    private function customerToken(Customer $customer): string
    {
        return $customer->createToken('phone', ['customer'])->plainTextToken;
    }
}
