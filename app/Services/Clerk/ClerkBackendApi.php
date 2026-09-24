<?php

namespace App\Services\Clerk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * The few Clerk Backend API calls `php artisan clerk:smoke-test` needs to
 * sign real session tokens: create a throwaway user, open a session for it,
 * mint a token, then clean up.
 *
 * Nothing in the request path uses this class — the API verifies session
 * tokens locally (ClerkTokenVerifier). Opening a session from the backend is
 * available on development instances only.
 *
 * Calls are not retried: each one changes state on Clerk, and a duplicate
 * user or session would be left behind.
 */
final class ClerkBackendApi
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $apiUrl,
    ) {}

    /**
     * @return string The new Clerk user id (`user_…`).
     *
     * @throws RequestException
     */
    public function createUser(string $email): string
    {
        return $this->request()
            ->post('/users', [
                'email_address' => [$email],
                'skip_password_requirement' => true,
            ])
            ->throw()
            ->json('id');
    }

    /**
     * @return string The new session id (`sess_…`).
     *
     * @throws RequestException
     */
    public function createSession(string $userId): string
    {
        return $this->request()
            ->post('/sessions', ['user_id' => $userId])
            ->throw()
            ->json('id');
    }

    /**
     * A session token exactly like the one the apps get from `getToken()`,
     * custom claims included.
     *
     * @throws RequestException
     */
    public function sessionToken(string $sessionId, int $expiresInSeconds = 120): string
    {
        return $this->request()
            ->post("/sessions/{$sessionId}/tokens", ['expires_in_seconds' => $expiresInSeconds])
            ->throw()
            ->json('jwt');
    }

    /**
     * @throws RequestException
     */
    public function revokeSession(string $sessionId): void
    {
        $this->request()->post("/sessions/{$sessionId}/revoke")->throw();
    }

    /**
     * @throws RequestException
     */
    public function deleteUser(string $userId): void
    {
        $this->request()->delete("/users/{$userId}")->throw();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }
}
