<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_and_record_the_login_time(): void
    {
        $this->travelTo('2026-09-15 10:00:00');
        $admin = Admin::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('password-1234'),
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password-1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token']);
        $this->assertArrayNotHasKey('password', $response->json('data'));

        $this->assertSame(1, $admin->tokens()->count());
        $this->assertSame('2026-09-15 10:00:00', $admin->fresh()->last_login_at->toDateTimeString());
    }

    public function test_wrong_password_returns_422_and_issues_no_token(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('password-1234'),
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, $admin->tokens()->count());
        $this->assertNull($admin->fresh()->last_login_at);
    }

    public function test_public_registration_endpoint_returns_404(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('admins', ['email' => 'intruder@example.com']);
    }

    public function test_me_returns_401_without_a_token(): void
    {
        $response = $this->getJson('/api/v1/admin/auth/me');

        $response->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_returns_the_authenticated_admin(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/auth/me');

        $response->assertOk()->assertJsonPath('data.id', $admin->id);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $admin = Admin::factory()->create();
        $keptToken = $admin->createToken('laptop')->plainTextToken;
        $revokedToken = $admin->createToken('phone')->plainTextToken;

        $this->withToken($revokedToken)->postJson('/api/v1/admin/auth/logout')->assertOk();

        $this->assertSame(1, $admin->tokens()->count());

        // These are separate requests sharing one container, so drop the
        // resolved guard to force each token to be checked again.
        $this->app['auth']->forgetGuards();
        $this->withToken($keptToken)->getJson('/api/v1/admin/auth/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($revokedToken)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
    }

    public function test_logout_all_revokes_every_token(): void
    {
        $admin = Admin::factory()->create();
        $admin->createToken('laptop');
        $token = $admin->createToken('phone')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/auth/logout-all');

        $response->assertOk();

        $this->assertSame(0, $admin->tokens()->count());
    }
}
