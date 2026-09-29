<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every API error has the contract's shape, whatever raised it
 * (API contract §1.3).
 */
class ApiRoutingTest extends TestCase
{
    public function test_ping_is_public(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertOk()->assertJsonPath('message', 'pong');
    }

    public function test_callers_sharing_an_ip_address_do_not_share_a_rate_limit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->withToken('token-of-the-first-phone')->getJson('/api/v1/ping')->assertOk();
        }

        $this->withToken('token-of-the-first-phone')->getJson('/api/v1/ping')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        $this->withToken('token-of-the-second-phone')->getJson('/api/v1/ping')->assertOk();
    }

    public function test_an_unknown_endpoint_answers_not_found_in_the_error_shape(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response->assertNotFound()->assertExactJsonStructure(['error' => ['code', 'message']]);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_field_errors_list_a_short_rule_name_per_field(): void
    {
        Route::middleware('api')->post('/api/v1/validation-probe', fn () => request()->validate([
            'name' => ['required'],
            'phone' => ['required', 'regex:/^\d+$/'],
        ]));

        $response = $this->postJson('/api/v1/validation-probe', ['phone' => 'abc']);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.fields', [
                'name' => ['required'],
                'phone' => ['format'],
            ]);
    }

    public function test_an_unexpected_failure_hides_its_cause_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/v1/failure-probe', fn () => throw new \RuntimeException('database password is hunter2'));

        $response = $this->getJson('/api/v1/failure-probe');

        $response->assertServerError()->assertExactJson([
            'error' => ['code' => 'SERVER_ERROR', 'message' => 'Unexpected server error.'],
        ]);
    }
}
