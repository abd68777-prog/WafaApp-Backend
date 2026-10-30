<?php

namespace Tests\Feature\Security;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\BusinessType;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\DeletionRequest;
use App\Models\Icon;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Stamp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every API route, checked the same way: who may call it, and what everyone
 * else gets back. A route nobody classified fails the first test, so a new
 * endpoint cannot ship without protection by accident.
 */
class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Each test sends one request per route, far past the per-minute limit;
     * rate limiting has its own tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::for('api', fn () => Limit::none());
    }

    /**
     * Routes anyone may call, each for a stated reason.
     */
    private const PUBLIC_ROUTES = [
        'GET api/v1/ping',
        // Before sign-in: the app needs its settings and the code flow.
        'GET api/v1/customer/config',
        'POST api/v1/customer/auth/otp',
        'POST api/v1/customer/auth/verify',
        // The web page Google Play requires; owning the number is proved by the code.
        'POST api/v1/public/account-deletion/otp',
        'POST api/v1/public/account-deletion',
        // Authenticated by its Svix signature instead.
        'POST api/v1/webhooks/clerk',
    ];

    public function test_every_route_is_either_deliberately_public_or_protected(): void
    {
        $unprotected = collect($this->apiRoutes())
            ->reject(fn (RoutingRoute $route) => in_array($this->key($route), self::PUBLIC_ROUTES, true))
            ->reject(fn (RoutingRoute $route) => $this->audience($route) !== null)
            ->map(fn (RoutingRoute $route) => $this->key($route))
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'Routes without authentication that are not on the public list.');
        $this->assertCount(count(self::PUBLIC_ROUTES), collect($this->apiRoutes())->filter(fn ($route) => $this->audience($route) === null));
    }

    public function test_without_credentials_every_protected_route_answers_unauthenticated(): void
    {
        $ids = $this->fixtures();

        $this->assertEveryRoute(fn (RoutingRoute $route) => $this->audience($route) !== null, function (RoutingRoute $route) use ($ids): ?string {
            $response = $this->call_($route, $ids);

            return $this->expectError($response, 401, 'UNAUTHENTICATED');
        });
    }

    public function test_the_webhook_refuses_an_unsigned_request(): void
    {
        $this->postJson('/api/v1/webhooks/clerk', ['type' => 'user.deleted', 'data' => ['id' => 'user_x']])
            ->assertStatus(400)
            ->assertExactJsonStructure(['error' => ['code', 'message']]);
    }

    public function test_a_customer_token_opens_no_merchant_or_dashboard_route(): void
    {
        $ids = $this->fixtures();
        $token = Customer::factory()->consented()->create()->createToken('phone', ['customer'])->plainTextToken;

        $this->assertEveryRoute(fn (RoutingRoute $route) => in_array($this->audience($route), ['merchant', 'admin'], true), function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 401, 'UNAUTHENTICATED');
        });
    }

    public function test_a_merchant_session_opens_no_customer_or_dashboard_route(): void
    {
        $ids = $this->fixtures();
        $token = $this->clerkToken('user_shop');

        $this->assertEveryRoute(fn (RoutingRoute $route) => $this->audience($route) === 'customer', function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 401, 'UNAUTHENTICATED');
        });

        $this->assertEveryRoute(fn (RoutingRoute $route) => $this->audience($route) === 'admin', function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 403, 'FORBIDDEN');
        });
    }

    public function test_a_dashboard_session_opens_no_customer_route_and_no_registered_shop_route(): void
    {
        $ids = $this->fixtures();
        AdminUser::factory()->superAdmin()->create(['clerk_user_id' => 'user_owner']);
        $token = $this->clerkToken('user_owner');

        $this->assertEveryRoute(fn (RoutingRoute $route) => $this->audience($route) === 'customer', function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 401, 'UNAUTHENTICATED');
        });

        // No shop belongs to this Clerk user, so the shop's own screens stay shut.
        $this->assertEveryRoute(fn (RoutingRoute $route) => in_array('clerk.merchant', $route->gatherMiddleware(), true), function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 403, 'REGISTRATION_INCOMPLETE');
        });
    }

    public function test_each_dashboard_role_reaches_exactly_the_routes_its_permissions_allow(): void
    {
        $ids = $this->fixtures();

        foreach (AdminRole::cases() as $role) {
            $clerkUserId = "user_{$role->value}";
            AdminUser::factory()->create(['clerk_user_id' => $clerkUserId, 'role' => $role]);
            $token = $this->clerkToken($clerkUserId);

            $this->assertEveryRoute(fn (RoutingRoute $route) => $this->audience($route) === 'admin', function (RoutingRoute $route) use ($ids, $token, $role): ?string {
                $permission = $this->permission($route);
                $allowed = $permission === null || in_array($permission, $role->permissions(), true);
                $response = $this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]);

                if (! $allowed) {
                    return $this->expectError($response, 403, 'FORBIDDEN');
                }

                return $response->status() === 403 || $response->status() === 401
                    ? "{$role->value} was refused ({$response->status()}) although it holds the permission"
                    : null;
            }, $role->value);
        }
    }

    public function test_the_pin_guarded_shop_screens_ask_for_the_pin(): void
    {
        $ids = $this->fixtures();
        $token = $this->clerkToken('user_shop');

        $this->assertEveryRoute(fn (RoutingRoute $route) => in_array('merchant.pin', $route->gatherMiddleware(), true), function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 403, 'PIN_REQUIRED');
        });
    }

    public function test_an_outdated_merchant_app_is_refused_on_every_shop_route(): void
    {
        $ids = $this->fixtures();
        Setting::write('merchant_min_app_version', '2.0.0');
        $token = $this->clerkToken('user_shop');

        $this->assertEveryRoute(fn (RoutingRoute $route) => $this->audience($route) === 'merchant', function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}", 'X-App-Version' => '1.9.9']), 426, 'APP_VERSION_UNSUPPORTED');
        });
    }

    public function test_a_customer_without_a_finished_profile_only_reaches_the_screens_that_finish_it(): void
    {
        $ids = $this->fixtures();
        $token = Customer::factory()->withoutProfile()->consented()->create()->createToken('phone', ['customer'])->plainTextToken;

        $this->assertEveryRoute(fn (RoutingRoute $route) => in_array('customer.ready', $route->gatherMiddleware(), true), function (RoutingRoute $route) use ($ids, $token): ?string {
            return $this->expectError($this->call_($route, $ids, ['Authorization' => "Bearer {$token}"]), 403, 'PROFILE_INCOMPLETE');
        });
    }

    /**
     * @return list<RoutingRoute>
     */
    private function apiRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
            ->values()
            ->all();
    }

    /**
     * @return 'customer'|'merchant'|'admin'|null
     */
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

    private function permission(RoutingRoute $route): ?AdminPermission
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                return AdminPermission::from(substr($middleware, 4));
            }
        }

        return null;
    }

    private function key(RoutingRoute $route): string
    {
        return collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->first().' '.$route->uri();
    }

    /**
     * One existing row per route parameter, so model binding never answers
     * 404 before the check under test runs.
     *
     * @return array<string, int|string>
     */
    private function fixtures(): array
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $card = Card::factory()->for($merchant)->create();
        $customer = Customer::factory()->consented()->create();
        $cycle = CardCycle::factory()->create(['card_id' => $card->id, 'customer_id' => $customer->id, 'stamps_count' => 1]);
        $stamp = Stamp::factory()->create(['card_cycle_id' => $cycle->id, 'card_id' => $card->id, 'customer_id' => $customer->id, 'merchant_id' => $merchant->id]);

        return [
            'merchant' => $merchant->id,
            'card' => $card->id,
            'customer' => $customer->id,
            'cycle' => $cycle->id,
            'stamp' => $stamp->id,
            'payment' => Payment::factory()->for($merchant)->create()->id,
            'package' => Package::factory()->create()->id,
            'icon' => Icon::factory()->create()->id,
            'businessType' => BusinessType::factory()->create()->id,
            'adminUser' => AdminUser::factory()->create()->id,
            'deletionRequest' => DeletionRequest::factory()->create()->id,
            'notification' => '0199a1b2-0000-7000-8000-000000000000',
            'token' => 'fcm-token-that-does-not-exist',
        ];
    }

    /**
     * @param  array<string, int|string>  $ids
     * @param  array<string, string>  $headers
     */
    private function call_(RoutingRoute $route, array $ids, array $headers = []): TestResponse
    {
        $uri = preg_replace_callback('/\{(\w+)\??\}/', fn (array $match) => (string) ($ids[$match[1]] ?? 1), $route->uri());
        [$method] = explode(' ', $this->key($route));

        $this->app['auth']->forgetGuards();

        return $this->json($method, '/'.$uri, [], $headers);
    }

    private function expectError(TestResponse $response, int $status, string $code): ?string
    {
        $body = $response->json();
        $shapeOk = is_array($body) && array_keys($body) === ['error']
            && is_string($body['error']['code'] ?? null) && is_string($body['error']['message'] ?? null);

        if ($response->status() === $status && $shapeOk && $body['error']['code'] === $code) {
            return null;
        }

        return "expected {$status} {$code}, got {$response->status()} ".substr((string) $response->getContent(), 0, 160);
    }

    /**
     * Runs the check on every matching route and reports all the failures at
     * once, instead of stopping at the first.
     *
     * @param  callable(RoutingRoute): bool  $filter
     * @param  callable(RoutingRoute): ?string  $check
     */
    private function assertEveryRoute(callable $filter, callable $check, string $label = ''): void
    {
        $routes = array_filter($this->apiRoutes(), $filter);
        $this->assertNotEmpty($routes, 'No route matched the filter.');

        $failures = [];

        foreach ($routes as $route) {
            $failure = $check($route);

            if ($failure !== null) {
                $failures[] = trim("{$label} {$this->key($route)}: {$failure}");
            }
        }

        $this->assertSame([], $failures, count($failures).' of '.count($routes).' routes failed.');
    }
}
