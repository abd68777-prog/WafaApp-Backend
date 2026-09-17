<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_admin_linked_to_the_clerk_user(): void
    {
        $admin = Admin::factory()->create(['clerk_user_id' => 'user_owner']);

        $response = $this->withToken($this->clerkToken('user_owner'))->getJson('/api/v1/admin/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.email', $admin->email);
    }

    public function test_a_clerk_user_without_admin_access_gets_403(): void
    {
        Admin::factory()->create(['clerk_user_id' => 'user_owner']);

        // Merchants sign in to the same Clerk application, so a valid Clerk
        // session alone must not grant admin access.
        $response = $this->withToken($this->clerkToken('user_some_merchant'))->getJson('/api/v1/admin/auth/me');

        $response->assertForbidden()->assertJsonPath('message', 'This account does not have admin access.');
    }

    public function test_a_customer_sanctum_token_gets_401(): void
    {
        $token = Customer::factory()->create()->createToken('phone', ['customer'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/auth/me');

        $response->assertUnauthorized();
    }

    public function test_password_login_no_longer_exists(): void
    {
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password-1234',
        ]);

        $response->assertNotFound();
    }

    public function test_public_registration_endpoint_returns_404(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('admins', ['email' => 'intruder@example.com']);
    }
}
