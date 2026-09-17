<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * One RSA key pair per test process: generating it is the slow part.
     *
     * @var array{private: string, public: string}|null
     */
    private static ?array $clerkKeyPair = null;

    /**
     * Point Clerk verification at a key pair the tests control, so session
     * tokens can be signed exactly the way Clerk signs them.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clerk.jwt_key' => self::clerkKeyPair()['public'],
            'services.clerk.authorized_parties' => ['http://localhost:3000'],
            'services.clerk.clock_skew' => 5,
        ]);
    }

    /**
     * A Clerk session token (RS256) for the given Clerk user.
     *
     * Claims passed in override the defaults; pass `null` to leave one unset.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function clerkToken(string $clerkUserId, array $claims = [], ?string $privateKey = null): string
    {
        $issuedAt = time();

        $payload = array_merge([
            'sub' => $clerkUserId,
            'sid' => 'sess_test',
            'iss' => 'https://clerk.test',
            'iat' => $issuedAt,
            'nbf' => $issuedAt - 5,
            'exp' => $issuedAt + 60,
        ], $claims);

        return JWT::encode(
            array_filter($payload, fn (mixed $value): bool => $value !== null),
            $privateKey ?? self::clerkKeyPair()['private'],
            'RS256',
        );
    }

    /**
     * @return array{private: string, public: string}
     */
    protected static function clerkKeyPair(): array
    {
        return self::$clerkKeyPair ??= self::generateRsaKeyPair();
    }

    /**
     * @return array{private: string, public: string}
     */
    protected static function generateRsaKeyPair(): array
    {
        // Windows PHP builds cannot find openssl.cnf on their own; it ships
        // next to php.exe. Linux (including the Docker image) needs nothing.
        $opensslConfig = getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $configArgs = is_file($opensslConfig) ? ['config' => $opensslConfig] : [];

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ...$configArgs,
        ]);

        openssl_pkey_export($key, $privateKey, null, $configArgs);

        return [
            'private' => $privateKey,
            'public' => openssl_pkey_get_details($key)['key'],
        ];
    }
}
