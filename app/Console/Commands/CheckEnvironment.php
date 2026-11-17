<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Checks that a server runs with its own settings rather than a developer's:
 * no debug pages, no localhost in any link the apps receive, real delivery
 * of codes and pushes. Run after changing a server's .env; a failure exits
 * non-zero so a deploy step can stop on it.
 */
#[Signature('app:check-environment')]
#[Description('Check the environment for debug mode, localhost links and development drivers')]
class CheckEnvironment extends Command
{
    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        $environment = (string) config('app.env');

        $this->expect(! in_array($environment, ['local', 'testing'], true), "APP_ENV is \"{$environment}\"; use staging or production.");
        $this->expect(! config('app.debug'), 'APP_DEBUG is on: error pages show secrets. Set APP_DEBUG=false.');
        $this->expectPublicHttps('APP_URL', (string) config('app.url'));

        foreach ((array) config('cors.allowed_origins') as $origin) {
            $this->expect(! $this->isLocal((string) $origin), "FRONTEND_URLS allows {$origin}.");
        }

        $parties = array_filter((array) config('services.clerk.authorized_parties'));
        $this->expect($parties !== [], 'CLERK_AUTHORIZED_PARTIES is empty: the admin dashboard cannot sign in.');

        foreach ($parties as $party) {
            $this->expect(! $this->isLocal((string) $party), "CLERK_AUTHORIZED_PARTIES allows {$party}.");
        }

        $this->expect(filled(config('services.clerk.jwt_key')), 'CLERK_JWT_KEY is empty: nobody can sign in with Clerk.');

        $logLevel = (string) config('logging.channels.'.config('logging.default').'.level', 'debug');
        $this->expect($logLevel !== 'debug', 'LOG_LEVEL is debug; use warning or error.');

        if (config('filesystems.media_disk') === 'public') {
            $this->expectPublicHttps('the media disk URL', (string) config('filesystems.disks.public.url'));
        }

        $this->warnUnless(config('otp.driver') !== 'log', 'OTP_DRIVER is log: customers receive no sign-in code.');
        $this->warnUnless(filled(config('services.clerk.webhook_secret')), 'CLERK_WEBHOOK_SECRET is empty: Clerk email changes and deletions are not synced.');
        $this->warnUnless(
            filled(config('firebase.projects.'.config('firebase.default').'.credentials')),
            'FIREBASE_CREDENTIALS is empty: notifications reach the inbox but no phone.',
        );

        foreach (Setting::query()->get(['key', 'value']) as $setting) {
            if (is_string($setting->value) && $this->isLocal($setting->value)) {
                $this->failures[] = "The \"{$setting->key}\" setting points to {$setting->value}.";
            }
        }

        return $this->report();
    }

    private function expectPublicHttps(string $name, string $url): void
    {
        $this->expect(str_starts_with($url, 'https://') && ! $this->isLocal($url), "{$name} is \"{$url}\"; use the environment's https domain.");
    }

    private function isLocal(string $value): bool
    {
        return preg_match('/localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\]/i', $value) === 1;
    }

    private function expect(bool $passes, string $failure): void
    {
        if (! $passes) {
            $this->failures[] = $failure;
        }
    }

    private function warnUnless(bool $passes, string $warning): void
    {
        if (! $passes) {
            $this->warnings[] = $warning;
        }
    }

    private function report(): int
    {
        foreach ($this->failures as $failure) {
            $this->components->error($failure);
        }

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($this->failures !== []) {
            return self::FAILURE;
        }

        $this->components->info('The environment is ready for '.config('app.env').'.');

        return self::SUCCESS;
    }
}
