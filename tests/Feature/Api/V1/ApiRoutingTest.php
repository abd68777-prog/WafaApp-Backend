<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ApiRoutingTest extends TestCase
{
    public function test_ping_is_public(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertOk()->assertJsonPath('message', 'pong');
    }

    public function test_unknown_endpoint_returns_404_as_json(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()->assertJsonPath('message', 'Endpoint not found.');
    }
}
