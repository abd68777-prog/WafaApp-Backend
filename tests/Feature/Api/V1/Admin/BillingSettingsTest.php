<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\PaymentAccount;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The payment screen's details and the subscription timings, set by the
 * Super Admin (requirements §5.5): the exchange rate, and where each payment
 * method takes the money.
 */
class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'role' => AdminRole::SuperAdmin]);
    }

    public function test_the_super_admin_updates_the_rate_and_the_timings(): void
    {
        Setting::write('exchange_rate_syp', 13000);

        $this->asOwner()->patchJson('/api/v1/admin/settings/billing', [
            'exchange_rate_syp' => 14500,
            'grace_days' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.exchange_rate_syp', '14500.00')
            ->assertJsonPath('data.grace_days', 5)
            ->assertJsonPath('data.trial_days', 14)
            ->assertJsonMissingPath('data.syriatel_cash_number')
            ->assertJsonMissingPath('data.bank_transfer_details');

        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')
            ->assertJsonPath('data.payment.exchange_rate_syp', '14500.00');

        $log = AuditLog::query()->sole();
        $this->assertSame('13000.00', $log->before['exchange_rate_syp']);
        $this->assertSame('14500.00', $log->after['exchange_rate_syp']);
    }

    public function test_the_settings_list_both_methods_paused_until_they_are_filled_in(): void
    {
        $this->asOwner()->getJson('/api/v1/admin/settings/billing')
            ->assertOk()
            ->assertJsonPath('data.payment_methods', [
                ['key' => 'sham_cash', 'recipient_name' => null, 'account' => null, 'qr_url' => null, 'note' => null, 'name' => 'شام كاش', 'is_active' => false, 'sort_order' => 1],
                ['key' => 'haram', 'recipient_name' => null, 'account' => null, 'qr_url' => null, 'note' => null, 'name' => 'الهرم', 'is_active' => false, 'sort_order' => 2],
            ]);
    }

    public function test_a_filled_in_method_is_offered_to_merchants_in_the_chosen_order(): void
    {
        $this->asOwner()->patchJson('/api/v1/admin/settings/payment-methods/haram', [
            'recipient_name' => 'محمد أحمد',
            'account' => '0933123456',
            'note' => 'اكتب اسم المحل بالملاحظات',
            'sort_order' => 0,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.key', 'haram')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.recipient_name', 'محمد أحمد');

        $this->asOwner()->patchJson('/api/v1/admin/settings/payment-methods/sham_cash', ['account' => 'SC-998877', 'is_active' => true])->assertOk();

        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')
            ->assertJsonPath('data.payment.methods', [
                ['key' => 'haram', 'recipient_name' => 'محمد أحمد', 'account' => '0933123456', 'qr_url' => null, 'note' => 'اكتب اسم المحل بالملاحظات'],
                ['key' => 'sham_cash', 'recipient_name' => null, 'account' => 'SC-998877', 'qr_url' => null, 'note' => null],
            ]);

        $this->asOwner()->patchJson('/api/v1/admin/settings/payment-methods/haram', ['is_active' => false])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')
            ->assertJsonCount(1, 'data.payment.methods')
            ->assertJsonPath('data.payment.methods.0.key', 'sham_cash');

        $log = AuditLog::query()->where('action', 'settings.payment_method_updated')->oldest('id')->first();
        $this->assertSame(['method' => 'haram', 'is_active' => false, 'sort_order' => 2, 'recipient_name' => null, 'account' => null, 'qr_path' => null, 'note' => null], $log->before);
        $this->assertSame('0933123456', $log->after['account']);
    }

    public function test_a_method_with_nowhere_to_pay_cannot_be_offered(): void
    {
        $this->asOwner()->patchJson('/api/v1/admin/settings/payment-methods/sham_cash', ['is_active' => true])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['account' => ['invalid']]);

        PaymentAccount::query()->where('method', 'sham_cash')->update(['is_active' => true, 'account' => 'SC-1']);

        $this->asOwner()->patchJson('/api/v1/admin/settings/payment-methods/sham_cash', ['account' => null])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['account' => ['invalid']]);

        $this->asOwner()->patchJson('/api/v1/admin/settings/payment-methods/transfer', ['is_active' => false])->assertNotFound();
    }

    public function test_the_sham_cash_qr_is_uploaded_replaced_and_removed(): void
    {
        $disk = config('filesystems.media_disk');
        Storage::fake($disk);

        $first = $this->uploadQr()->assertOk()->json('data.qr_url');
        $firstPath = PaymentAccount::query()->where('method', 'sham_cash')->value('qr_path');
        $this->assertStringContainsString('payment-methods/', $first);

        $this->uploadQr()->assertOk();
        Storage::disk($disk)->assertMissing($firstPath);
        $secondPath = PaymentAccount::query()->where('method', 'sham_cash')->value('qr_path');
        Storage::disk($disk)->assertExists($secondPath);

        $this->asOwner()->deleteJson('/api/v1/admin/settings/payment-methods/sham_cash/qr')
            ->assertOk()
            ->assertJsonPath('data.qr_url', null);
        Storage::disk($disk)->assertMissing($secondPath);

        $this->asOwner()->post('/api/v1/admin/settings/payment-methods/sham_cash/qr', [
            'qr' => UploadedFile::fake()->create('qr.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_values_are_validated_and_only_the_super_admin_may_change_them(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);

        $this->asOwner()->patchJson('/api/v1/admin/settings/billing', ['trial_days' => 0])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.trial_days', ['min']);

        $this->withToken($this->clerkToken('user_admin'))->getJson('/api/v1/admin/settings/billing')->assertForbidden();
        $this->withToken($this->clerkToken('user_admin'))
            ->patchJson('/api/v1/admin/settings/payment-methods/haram', ['note' => 'x'])
            ->assertForbidden();
    }

    private function uploadQr(): TestResponse
    {
        return $this->asOwner()->post('/api/v1/admin/settings/payment-methods/sham_cash/qr', [
            'qr' => UploadedFile::fake()->image('qr.png', 300, 300),
        ], ['Accept' => 'application/json']);
    }

    private function asOwner(): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->clerkToken('user_owner'));
    }
}
