<?php

namespace Tests\Feature\Console;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `app:check-environment` fails a server still running on a developer's
 * settings, and passes one set up for its own domain.
 */
class CheckEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://api.wafacard.app',
            'cors.allowed_origins' => ['https://admin.wafacard.app'],
            'services.clerk.authorized_parties' => ['https://admin.wafacard.app'],
            'services.clerk.jwt_key' => 'a-public-key',
            'services.clerk.webhook_secret' => 'whsec_test',
            'logging.default' => 'stderr',
            'logging.channels.stderr.level' => 'warning',
            'filesystems.media_disk' => 'cloudinary',
            'otp.driver' => 'lightotp',
            'firebase.default' => 'app',
            'firebase.projects.app.credentials' => 'secrets/firebase.json',
        ]);
    }

    public function test_a_server_set_up_for_its_domain_passes(): void
    {
        $this->artisan('app:check-environment')
            ->expectsOutputToContain('The environment is ready for production.')
            ->assertSuccessful();
    }

    public function test_debug_mode_and_localhost_links_fail_the_check(): void
    {
        config([
            'app.debug' => true,
            'app.url' => 'http://localhost:8000',
            'cors.allowed_origins' => ['https://admin.wafacard.app', 'http://localhost:3000'],
            'logging.channels.stderr.level' => 'debug',
        ]);
        Setting::write('merchant_app_download_url', 'http://127.0.0.1:8000/app.apk');

        $this->artisan('app:check-environment')
            ->expectsOutputToContain('APP_DEBUG is on')
            ->expectsOutputToContain('APP_URL is "http://localhost:8000"')
            ->expectsOutputToContain('FRONTEND_URLS allows http://localhost:3000')
            ->expectsOutputToContain('LOG_LEVEL is debug')
            ->expectsOutputToContain('The "merchant_app_download_url" setting points to http://127.0.0.1:8000/app.apk')
            ->assertFailed();
    }

    public function test_development_drivers_only_warn(): void
    {
        config(['otp.driver' => 'log', 'firebase.projects.app.credentials' => null]);

        $this->artisan('app:check-environment')
            ->expectsOutputToContain('OTP_DRIVER is log')
            ->expectsOutputToContain('FIREBASE_CREDENTIALS is empty')
            ->assertSuccessful();
    }
}
