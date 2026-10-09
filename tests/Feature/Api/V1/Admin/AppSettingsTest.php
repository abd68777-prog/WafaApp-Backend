<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stamps, campaigns, the merchant app's versions and the legal links, set by
 * the Super Admin (requirements §5.5).
 */
class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
    }

    public function test_the_super_admin_changes_only_what_is_sent_and_the_apps_follow(): void
    {
        $this->asOwner()->getJson('/api/v1/admin/settings/app')
            ->assertOk()
            ->assertJsonPath('data.card_stamps_max', 10)
            ->assertJsonPath('data.qr_period_seconds', 60);

        $this->asOwner()->patchJson('/api/v1/admin/settings/app', [
            'card_stamps_max' => 12,
            'campaign_title_max' => 80,
            'merchant_min_app_version' => '1.4.0',
            'privacy_policy_url' => 'https://wafa.example/privacy',
        ])
            ->assertOk()
            ->assertJsonPath('data.card_stamps_max', 12)
            ->assertJsonPath('data.card_stamps_min', 3)
            ->assertJsonPath('data.merchant_min_app_version', '1.4.0');

        $log = AuditLog::query()->sole();
        $this->assertSame('settings.app_updated', $log->action);
        $this->assertSame(10, $log->before['card_stamps_max']);
        $this->assertSame(12, $log->after['card_stamps_max']);

        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))
            ->withHeader('X-App-Version', '1.4.0')
            ->getJson('/api/v1/merchant/lookups')
            ->assertJsonPath('data.limits.card_stamps_max', 12)
            ->assertJsonPath('data.limits.campaign_title_max', 80)
            ->assertJsonPath('data.links.privacy_policy_url', 'https://wafa.example/privacy');
    }

    public function test_the_stamps_minimum_never_passes_the_maximum_and_formats_are_checked(): void
    {
        Setting::write('card_stamps_max', 8);

        $this->asOwner()->patchJson('/api/v1/admin/settings/app', ['card_stamps_min' => 9])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['card_stamps_min' => ['invalid']]);

        $this->asOwner()->patchJson('/api/v1/admin/settings/app', [
            'merchant_min_app_version' => 'v2',
            'merchant_app_download_url' => 'http://insecure.example/app.apk',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['merchant_min_app_version' => ['format'], 'merchant_app_download_url' => ['format']]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_only_the_super_admin_sees_the_settings(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);

        $this->withToken($this->clerkToken('user_admin'))->getJson('/api/v1/admin/settings/app')->assertForbidden();
    }

    private function asOwner(): self
    {
        return $this->withToken($this->clerkToken('user_owner'));
    }
}
