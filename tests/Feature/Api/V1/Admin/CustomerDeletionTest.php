<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\DeletionSource;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeletionRequest;
use App\Models\OtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a customer account without the app (privacy policy §10): by the
 * team for a request that came by email or support, or by the customer on
 * the web page with a WhatsApp code. Either way it happens at once.
 */
class CustomerDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+963933000111';

    public function test_the_team_deletes_a_customer_who_asked_by_email(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        $customer = Customer::factory()->consented()->create(['phone' => self::PHONE]);

        $this->withToken($this->clerkToken('user_admin'))
            ->postJson("/api/v1/admin/customers/{$customer->id}/deletion", ['source' => 'email', 'note' => 'رسالة 12/10'])
            ->assertCreated()
            ->assertJsonPath('data.subject_type', 'customer')
            ->assertJsonPath('data.subject_name', null)
            ->assertJsonPath('data.source', 'email')
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.note', 'رسالة 12/10');

        $this->assertNull(Customer::withTrashed()->find($customer->id)->phone);
        $this->assertSame('customer.deleted', AuditLog::query()->sole()->action);
    }

    public function test_a_customer_deletes_their_account_from_the_web_page_with_a_code(): void
    {
        $customer = Customer::factory()->consented()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/public/account-deletion/otp', ['phone' => '0933000111'])
            ->assertAccepted()
            ->assertJsonStructure(['data' => ['expires_in_seconds', 'resend_after_seconds']]);
        $this->assertSame(1, OtpCode::query()->where('phone', self::PHONE)->count());

        // Replace the sent code with one the test knows.
        OtpCode::query()->delete();
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/public/account-deletion', ['phone' => '0933000111', 'code' => '654321'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'OTP_INVALID');
        $this->assertNotNull($customer->fresh());

        $this->postJson('/api/v1/public/account-deletion', ['phone' => '0933000111', 'code' => '123456'])->assertNoContent();

        $this->assertSoftDeleted($customer);
        $this->assertNull(Customer::withTrashed()->find($customer->id)->phone);
        $this->assertSame(DeletionSource::Web, DeletionRequest::query()->sole()->source);
    }

    public function test_a_number_without_an_account_gets_the_same_answer_and_no_message(): void
    {
        $this->postJson('/api/v1/public/account-deletion/otp', ['phone' => '0933000111'])
            ->assertAccepted()
            ->assertJsonStructure(['data' => ['expires_in_seconds', 'resend_after_seconds']]);

        $this->assertSame(0, OtpCode::query()->count());

        $this->postJson('/api/v1/public/account-deletion', ['phone' => '0933000111', 'code' => '123456'])
            ->assertJsonPath('error.code', 'OTP_EXPIRED');
    }
}
