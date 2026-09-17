<?php

namespace Tests\Feature\Api\V1;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clerk session token verification, exercised through a route that only
 * needs the `clerk` middleware.
 */
class ClerkAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/merchant/auth/me';

    public function test_a_native_app_token_without_an_origin_is_accepted(): void
    {
        $response = $this->withToken($this->clerkToken('user_1'))->getJson(self::ENDPOINT);

        $response->assertOk();
    }

    public function test_a_token_from_an_allowed_origin_is_accepted(): void
    {
        $token = $this->clerkToken('user_1', ['azp' => 'http://localhost:3000']);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertOk();
    }

    public function test_a_token_from_an_unlisted_origin_returns_401(): void
    {
        $token = $this->clerkToken('user_1', ['azp' => 'https://evil.example']);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_a_request_without_a_token_returns_401(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_an_expired_token_returns_401(): void
    {
        $token = $this->clerkToken('user_1', [
            'iat' => time() - 300,
            'nbf' => time() - 300,
            'exp' => time() - 120,
        ]);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_token_that_is_not_valid_yet_returns_401(): void
    {
        $token = $this->clerkToken('user_1', ['nbf' => time() + 120]);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_token_signed_with_another_key_returns_401(): void
    {
        $token = $this->clerkToken('user_1', privateKey: self::generateRsaKeyPair()['private']);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_token_signed_with_a_shared_secret_returns_401(): void
    {
        $token = JWT::encode([
            'sub' => 'user_1',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 60,
        ], str_repeat('s', 64), 'HS256');

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_token_without_an_expiry_returns_401(): void
    {
        $token = $this->clerkToken('user_1', ['exp' => null]);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_token_without_a_subject_returns_401(): void
    {
        $token = $this->clerkToken('');

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_pending_session_returns_401(): void
    {
        $token = $this->clerkToken('user_1', ['sts' => 'pending']);

        $response = $this->withToken($token)->getJson(self::ENDPOINT);

        $response->assertUnauthorized();
    }

    public function test_a_one_line_env_key_with_escaped_line_breaks_is_restored(): void
    {
        $pem = self::clerkKeyPair()['public'];
        $_SERVER['CLERK_JWT_KEY'] = str_replace("\n", '\n', $pem);

        try {
            $services = require config_path('services.php');
        } finally {
            unset($_SERVER['CLERK_JWT_KEY']);
        }

        $this->assertSame($pem, $services['clerk']['jwt_key']);
    }

    public function test_a_missing_clerk_key_returns_500_instead_of_rejecting_every_user(): void
    {
        config(['services.clerk.jwt_key' => null]);

        $response = $this->withToken($this->clerkToken('user_1'))->getJson(self::ENDPOINT);

        $response->assertServerError();
    }
}
