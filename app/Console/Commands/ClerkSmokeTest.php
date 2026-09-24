<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\Merchant;
use App\Models\TrialEmailHash;
use App\Services\Clerk\ClerkBackendApi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the merchant and dashboard flows against this API with session tokens
 * signed by the real Clerk instance, then removes everything it created.
 *
 * It proves what the test suite cannot: that CLERK_JWT_KEY belongs to the
 * instance, that the session token carries the `email` claim, and what Clerk
 * actually puts in `fva`.
 *
 * It writes to the database the API uses, so it must run where the API runs:
 * next to `php artisan serve`, or inside the Docker `app` container with
 * `--base-url=http://web`.
 */
#[Signature('clerk:smoke-test
    {--base-url= : Root URL of the API to test (default: APP_URL; http://web inside Docker)}
    {--keep : Leave the test users, shop and dashboard account in place}')]
#[Description('Test the merchant and dashboard flows with real Clerk session tokens')]
class ClerkSmokeTest extends Command
{
    private const PIN = '4321';

    private ClerkBackendApi $clerk;

    private string $apiUrl;

    private int $failures = 0;

    /** @var list<string> */
    private array $testUserIds = [];

    /** @var list<string> */
    private array $sessionIds = [];

    private ?string $merchantEmail = null;

    private ?AdminUser $supportAccount = null;

    private bool $sharesDatabase = true;

    public function handle(ClerkBackendApi $clerk): int
    {
        $refusal = $this->refusal();

        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }

        $this->clerk = $clerk;
        $this->apiUrl = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/').'/api/v1';

        $this->components->info("Testing {$this->apiUrl} with the Clerk instance behind CLERK_SECRET_KEY.");

        try {
            $this->runScenario();
        } catch (Throwable $exception) {
            $this->recordFailure('Stopped by an unexpected error', $exception->getMessage());
        } finally {
            $this->option('keep') ? $this->components->warn('--keep: nothing was removed.') : $this->cleanUp();
        }

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error("{$this->failures} check(s) failed.");

            return self::FAILURE;
        }

        $this->components->success('Every check passed.');

        return self::SUCCESS;
    }

    /**
     * The command creates and deletes users, shops and dashboard accounts, so
     * it only ever runs against a development Clerk instance and never in
     * production.
     */
    private function refusal(): ?string
    {
        if (app()->isProduction()) {
            return 'Refusing to run in production: this command creates and deletes data.';
        }

        if (! Str::startsWith((string) config('services.clerk.secret_key'), 'sk_test_')) {
            return 'Set CLERK_SECRET_KEY to the secret key of a development instance (sk_test_…).';
        }

        if (blank(config('services.clerk.jwt_key'))) {
            return 'Set CLERK_JWT_KEY to the JWT public key of the same Clerk instance.';
        }

        return null;
    }

    private function runScenario(): void
    {
        $suffix = Str::lower(Str::random(8));
        $this->merchantEmail = "wafa-smoke-merchant-{$suffix}+clerk_test@example.com";
        $adminEmail = "wafa-smoke-admin-{$suffix}+clerk_test@example.com";

        $this->section('Clerk instance');
        $merchantToken = $this->tokenForNewUser($this->merchantEmail);
        $adminToken = $this->tokenForNewUser($adminEmail);
        $this->inspectClaims($merchantToken);

        $this->section('Token verification');
        $me = $this->api($merchantToken)->get('/merchant/auth/me');

        if ($me->unauthorized()) {
            $this->recordFailure('The API accepts the Clerk token', 'got 401: CLERK_JWT_KEY is not the public key of this Clerk instance');

            return;
        }

        $this->check('The API accepts the Clerk token', $me->ok() && $me->json('registered') === false, $this->describe($me));

        $this->section('Merchant registration');
        $this->merchantFlow($merchantToken, $suffix);

        if (! $this->sharesDatabase) {
            return;
        }

        $this->section('Dashboard account linked on first sign-in');
        $this->dashboardFlow($adminToken, $adminEmail);

        $ownerId = config('auth.platform_admin.clerk_user_id');

        if (filled($ownerId)) {
            $this->section('Platform owner');
            $this->ownerCheck((string) $ownerId);
        }
    }

    private function merchantFlow(string $token, string $suffix): void
    {
        $api = $this->api($token);

        $business = $api->post('/merchant/registration/business', [
            'business_name' => "Smoke Test Shop {$suffix}",
            'business_type_id' => $api->get('/lookups/business-types')->json('data.0.id'),
            'governorate_id' => $api->get('/lookups/governorates')->json('data.0.id'),
            'owner_name' => 'Smoke Test',
            'phone' => '+96390'.random_int(1000000, 9999999),
        ]);

        $this->check('Business details saved', $business->created(), $this->describe($business));
        $this->check(
            'Email copied from the Clerk token',
            $business->json('data.email') === $this->merchantEmail,
            'stored: '.var_export($business->json('data.email'), true),
        );

        if (! $business->created()) {
            return;
        }

        // Everything below cleans up through this process's database; if the
        // API writes elsewhere, cleaning up (and linking) cannot work.
        $this->sharesDatabase = Merchant::query()->whereKey($business->json('data.id'))->exists();

        if (! $this->sharesDatabase) {
            $this->recordFailure(
                'The API shares this database',
                "run the command where the API runs (inside Docker: --base-url=http://web); remove shop #{$business->json('data.id')} by hand",
            );

            return;
        }

        $package = $api->post('/merchant/registration/package', [
            'package_id' => $api->get('/lookups/packages')->json('data.0.id'),
        ]);

        $this->check(
            'Package chosen, trial started',
            $package->ok() && $package->json('trial_granted') === true && $package->json('data.status') === 'TRIAL',
            $this->describe($package),
        );

        $pin = $api->post('/merchant/registration/pin', ['pin' => self::PIN, 'pin_confirmation' => self::PIN]);
        $this->check('PIN set, registration done', $pin->json('registration_step') === 'done', $this->describe($pin));

        $unlock = $api->post('/merchant/pin/verify', ['pin' => self::PIN]);
        $this->check('PIN unlocks the protected tabs', filled($unlock->json('pin_token')), $this->describe($unlock));

        $change = $api->put('/merchant/pin', ['pin' => '8765', 'pin_confirmation' => '8765']);

        if ($change->json('code') === 'reverification_required') {
            // Not a failure of the API: the session was opened from the
            // backend, and Clerk decides what `fva` says for it.
            $this->components->twoColumnDetail(
                'PIN change after a fresh sign-in',
                '<fg=yellow;options=bold>SKIPPED</> this session has no fresh fva; test it from the app',
            );

            return;
        }

        $this->check('PIN change after a fresh sign-in', $change->ok(), $this->describe($change));
    }

    private function dashboardFlow(string $token, string $email): void
    {
        $this->supportAccount = AdminUser::query()->create([
            'name' => 'Smoke Test Support',
            'email' => $email,
            'role' => AdminRole::Support,
            'is_active' => true,
        ]);

        $me = $this->api($token)->get('/admin/auth/me');

        $this->check(
            'Account linked by its email',
            $me->ok() && $me->json('data.linked') === true && $me->json('data.role') === 'support',
            $this->describe($me),
        );
        $this->check(
            'Permissions of the role returned',
            in_array('view-merchants-and-customers', (array) $me->json('permissions'), true),
            json_encode($me->json('permissions')),
        );

        $accounts = $this->api($token)->get('/admin/admin-users');

        $this->check(
            'Support cannot manage dashboard accounts',
            $accounts->forbidden() && $accounts->json('code') === 'permission_denied',
            $this->describe($accounts),
        );
    }

    /**
     * The owner's real Clerk user gets a short session to confirm the seeder
     * linked it as super admin. The session is revoked afterwards; the user
     * itself is never touched.
     */
    private function ownerCheck(string $ownerId): void
    {
        try {
            $sessionId = $this->clerk->createSession($ownerId);
        } catch (Throwable $exception) {
            $this->recordFailure('Owner signs in to the dashboard', 'Clerk refused a session for ADMIN_CLERK_USER_ID: '.$exception->getMessage());

            return;
        }

        $this->sessionIds[] = $sessionId;
        $me = $this->api($this->clerk->sessionToken($sessionId))->get('/admin/auth/me');

        $this->check(
            'Owner signs in to the dashboard as super admin',
            $me->ok() && $me->json('data.role') === 'super_admin',
            $me->forbidden() ? 'no dashboard account: run php artisan db:seed --class=AdminUserSeeder' : $this->describe($me),
        );
    }

    private function tokenForNewUser(string $email): string
    {
        $userId = $this->clerk->createUser($email);
        $this->testUserIds[] = $userId;

        $sessionId = $this->clerk->createSession($userId);
        $this->sessionIds[] = $sessionId;

        $this->check("Test user and session created ({$email})", true);

        return $this->clerk->sessionToken($sessionId);
    }

    /**
     * Reads the token's claims without verifying it — verification is the
     * API's job, checked in the next step.
     */
    private function inspectClaims(string $token): void
    {
        $claims = json_decode(base64_decode(strtr(explode('.', $token)[1] ?? '', '-_', '+/')), true) ?: [];

        $this->check(
            'Session token carries the email claim',
            ($claims['email'] ?? null) === $this->merchantEmail,
            isset($claims['email']) ? '' : 'add {"email": "{{user.primary_email_address}}"} under Sessions → Customize session token',
        );

        $this->components->twoColumnDetail('Token version (v)', var_export($claims['v'] ?? null, true));
        $this->components->twoColumnDetail('Factor verification age (fva)', json_encode($claims['fva'] ?? null));
    }

    private function cleanUp(): void
    {
        $this->section('Clean-up');

        if ($this->testUserIds === []) {
            $this->components->twoColumnDetail('Nothing was created', '<fg=green;options=bold>DONE</>');

            return;
        }

        $this->attempt('Test shop removed', function (): void {
            $merchants = Merchant::withTrashed()->whereIn('clerk_user_id', $this->testUserIds)->get();

            foreach ($merchants as $merchant) {
                $merchant->subscriptionPeriods()->delete();
                $merchant->deviceTokens()->delete();
                $merchant->forceDelete();
            }

            if ($this->merchantEmail !== null) {
                TrialEmailHash::query()->where('email_hash', TrialEmailHash::fingerprint($this->merchantEmail))->delete();
            }
        });

        $this->attempt('Test dashboard account removed', function (): void {
            $this->supportAccount?->auditLogs()->delete();
            $this->supportAccount?->delete();
        });

        $this->attempt('Clerk sessions revoked', function (): void {
            foreach ($this->sessionIds as $sessionId) {
                $this->clerk->revokeSession($sessionId);
            }
        });

        $this->attempt('Clerk test users deleted', function (): void {
            foreach ($this->testUserIds as $userId) {
                $this->clerk->deleteUser($userId);
            }
        });
    }

    private function api(string $token): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->withToken($token)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function check(string $label, bool $passed, string $detail = ''): void
    {
        if ($passed) {
            $this->components->twoColumnDetail($label, '<fg=green;options=bold>PASS</>');

            return;
        }

        $this->recordFailure($label, $detail);
    }

    private function recordFailure(string $label, string $detail): void
    {
        $this->failures++;
        $this->components->twoColumnDetail($label, '<fg=red;options=bold>FAIL</> '.Str::limit($detail, 200));
    }

    private function attempt(string $label, callable $step): void
    {
        try {
            $step();
            $this->components->twoColumnDetail($label, '<fg=green;options=bold>DONE</>');
        } catch (Throwable $exception) {
            $this->components->twoColumnDetail($label, '<fg=yellow;options=bold>LEFT BEHIND</> '.Str::limit($exception->getMessage(), 200));
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("  <options=bold>{$title}</>");
    }

    private function describe(Response $response): string
    {
        return "HTTP {$response->status()}: ".Str::limit($response->body(), 200);
    }
}
