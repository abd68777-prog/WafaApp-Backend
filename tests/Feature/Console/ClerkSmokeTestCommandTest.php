<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The smoke test creates and deletes real Clerk users and database rows, so
 * it must refuse to start anywhere it could do harm. The scenario itself is
 * only meaningful against the real Clerk instance and is not faked here.
 */
class ClerkSmokeTestCommandTest extends TestCase
{
    /**
     * @return array<string, array{string|null}>
     */
    public static function unusableSecretKeys(): array
    {
        return [
            'missing' => [null],
            'production instance' => ['sk_live_abc123'],
        ];
    }

    #[DataProvider('unusableSecretKeys')]
    public function test_it_refuses_to_start_without_a_development_secret_key(?string $secretKey): void
    {
        Http::preventStrayRequests();
        config(['services.clerk.secret_key' => $secretKey]);

        $this->artisan('clerk:smoke-test')
            ->expectsOutputToContain('CLERK_SECRET_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_refuses_to_start_without_the_jwt_public_key(): void
    {
        Http::preventStrayRequests();
        config(['services.clerk.secret_key' => 'sk_test_abc123', 'services.clerk.jwt_key' => null]);

        $this->artisan('clerk:smoke-test')
            ->expectsOutputToContain('CLERK_JWT_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        Http::preventStrayRequests();
        config(['services.clerk.secret_key' => 'sk_test_abc123']);
        $this->app['env'] = 'production';

        $this->artisan('clerk:smoke-test')
            ->expectsOutputToContain('Refusing to run in production')
            ->assertFailed();

        Http::assertNothingSent();
    }
}
