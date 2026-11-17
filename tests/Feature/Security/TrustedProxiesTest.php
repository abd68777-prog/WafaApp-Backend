<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The client IP feeds the rate limits and the audit trail, so a forwarded IP
 * is believed only from the proxy in front of the API (Caddy, reaching nginx
 * from this machine or the Docker network), never from a client directly.
 */
class TrustedProxiesTest extends TestCase
{
    private const FORGED_IP = '203.0.113.9';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client-ip', fn (Request $request): string => (string) $request->ip());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function proxyAddresses(): array
    {
        return [
            'this machine' => ['127.0.0.1'],
            'the Docker bridge' => ['172.18.0.1'],
        ];
    }

    #[DataProvider('proxyAddresses')]
    public function test_the_proxy_in_front_passes_the_real_client_ip(string $proxyAddress): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $proxyAddress])
            ->withHeader('X-Forwarded-For', self::FORGED_IP)
            ->get('/_test/client-ip')
            ->assertOk()
            ->assertSeeText(self::FORGED_IP);
    }

    public function test_a_client_reaching_the_api_directly_cannot_forge_its_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', self::FORGED_IP)
            ->get('/_test/client-ip')
            ->assertOk()
            ->assertSeeText('198.51.100.7');
    }
}
