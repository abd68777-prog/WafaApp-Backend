<?php

namespace Tests\Feature\Security;

use App\Enums\AdminRole;
use App\Enums\ErrorCode;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Merchant;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every failure answers in the contract's one shape (API contract §1.3),
 * `{ "error": { "code", "message", "details"? } }`, as JSON, and outside
 * debug mode never says anything about the code, tables or models behind it.
 */
class ErrorShapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
        RateLimiter::for('api', fn () => Limit::none());
    }

    public function test_a_wrong_method_answers_in_the_error_shape(): void
    {
        $response = $this->deleteJson('/api/v1/ping');

        $this->assertErrorShape($response, 405);
        $this->assertStringNotContainsString('Symfony', (string) $response->getContent());
    }

    public function test_a_client_that_forgets_the_accept_header_still_gets_json(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $response->assertHeader('Content-Type', 'application/json');
        $this->assertErrorShape($response, 404, 'NOT_FOUND');

        $this->assertErrorShape($this->get('/api/v1/customer/me'), 401, 'UNAUTHENTICATED');
    }

    public function test_a_broken_json_body_is_a_validation_failure_not_a_crash(): void
    {
        $response = $this->call('POST', '/api/v1/customer/auth/otp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"phone": "0933');

        $this->assertErrorShape($response, 422, 'VALIDATION_FAILED');
        $response->assertJsonPath('error.details.fields.phone', ['required']);
    }

    public function test_a_missing_record_never_names_the_model_or_the_table(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
        AdminUser::factory()->paymentsReviewer()->create(['clerk_user_id' => 'user_reviewer']);
        $customerToken = Customer::factory()->consented()->create()->createToken('phone', ['customer'])->plainTextToken;
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $pinToken = app(PinUnlockToken::class)->issue($merchant)['token'];

        $headers = [
            'admin' => ['Authorization' => 'Bearer '.$this->clerkToken('user_owner')],
            'merchant' => ['Authorization' => 'Bearer '.$this->clerkToken('user_shop'), 'X-Pin-Token' => $pinToken],
            'customer' => ['Authorization' => "Bearer {$customerToken}"],
        ];

        $failures = [];

        foreach ($this->routesWithModelParameters() as $route) {
            $audience = $this->audience($route);

            // A super admin lacks review-payments by design; that role answers for it.
            if ($audience === 'admin' && str_contains($route->uri(), 'admin/payments/')) {
                $routeHeaders = ['Authorization' => 'Bearer '.$this->clerkToken('user_reviewer')];
            } else {
                $routeHeaders = $headers[$audience];
            }

            $uri = '/'.preg_replace('/\{\w+\}/', '999999', $route->uri());
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

            $this->app['auth']->forgetGuards();
            // A valid body where one is needed, so the lookup itself answers.
            $response = $this->json($method, $uri, ['message' => 'كل عام وأنت بخير', 'reason' => 'فحص'], $routeHeaders);
            $content = (string) $response->getContent();

            $leaks = preg_match('/App\\\\|Models|No query results|SQLSTATE|\.php/i', $content) === 1;
            $code = $response->json('error.code');

            if ($response->status() !== 404 || $code !== 'NOT_FOUND' || $leaks) {
                $failures[] = "{$method} {$uri}: {$response->status()} ".substr($content, 0, 140);
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_every_error_code_has_a_status_and_is_documented_for_the_apps(): void
    {
        $openapi = (string) file_get_contents(base_path('openapi.yaml'));
        $guide = (string) file_get_contents(base_path('api.md'));
        $undocumented = [];

        foreach (ErrorCode::cases() as $code) {
            $this->assertGreaterThanOrEqual(400, $code->status(), $code->value);

            if (! str_contains($openapi, "        - {$code->value}\n") && ! str_contains($openapi, "        - {$code->value}\r\n")) {
                $undocumented[] = "{$code->value} missing from the openapi ErrorCode enum";
            }

            if (! str_contains($guide, "`{$code->value}`")) {
                $undocumented[] = "{$code->value} missing from api.md";
            }
        }

        $this->assertSame([], $undocumented);
    }

    /**
     * @return list<RoutingRoute>
     */
    private function routesWithModelParameters(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
            ->filter(fn (RoutingRoute $route) => $this->audience($route) !== null)
            // A device token is a plain string, not a record lookup.
            ->filter(fn (RoutingRoute $route) => array_diff($route->parameterNames(), ['token']) !== [])
            ->values()
            ->all();
    }

    private function audience(RoutingRoute $route): ?string
    {
        $middleware = $route->gatherMiddleware();

        return match (true) {
            in_array('clerk.admin', $middleware, true) => 'admin',
            in_array('clerk', $middleware, true) => 'merchant',
            in_array('auth:sanctum', $middleware, true) => 'customer',
            default => null,
        };
    }

    private function assertErrorShape(TestResponse $response, int $status, ?string $code = null): void
    {
        $response->assertStatus($status);
        $body = $response->json();

        $this->assertIsArray($body);
        $this->assertSame(['error'], array_keys($body), (string) $response->getContent());
        $this->assertEmpty(array_diff(array_keys($body['error']), ['code', 'message', 'details']));
        $this->assertIsString($body['error']['code']);
        $this->assertIsString($body['error']['message']);

        if ($code !== null) {
            $this->assertSame($code, $body['error']['code']);
        }
    }
}
