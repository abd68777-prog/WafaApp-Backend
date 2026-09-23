<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\AdminUser;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_dashboard_user_behind_the_clerk_session(): void
    {
        $admin = AdminUser::factory()->superAdmin()->create(['clerk_user_id' => 'user_owner']);

        $response = $this->withToken($this->clerkToken('user_owner'))->getJson('/api/v1/admin/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.role', 'super_admin');
    }

    public function test_me_lists_the_permissions_of_the_role_and_records_the_sign_in(): void
    {
        $this->freezeTime();
        $admin = AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);

        $response = $this->withToken($this->clerkToken('user_support'))->getJson('/api/v1/admin/auth/me');

        $response->assertOk()->assertJsonPath('permissions', [
            'edit-business-identity',
            'edit-customer-birthdate',
            'view-merchants-and-customers',
            'reveal-customer-phone',
        ]);

        $this->assertSame(now()->toDateTimeString(), $admin->fresh()->last_login_at->toDateTimeString());
    }

    public function test_an_added_account_links_to_the_clerk_user_who_signs_in_with_its_email(): void
    {
        $admin = AdminUser::factory()->paymentsReviewer()->unlinked()->create(['email' => 'reviewer@wafa.test']);

        $response = $this->withToken($this->clerkToken('user_reviewer', ['email' => 'Reviewer@Wafa.test']))
            ->getJson('/api/v1/admin/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.linked', true);

        $this->assertSame('user_reviewer', $admin->fresh()->clerk_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'admin_user.linked',
            'subject_type' => 'admin',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_an_account_already_linked_is_not_taken_over_by_another_clerk_user_with_the_same_email(): void
    {
        $admin = AdminUser::factory()->create(['clerk_user_id' => 'user_owner', 'email' => 'owner@wafa.test']);

        $response = $this->withToken($this->clerkToken('user_intruder', ['email' => 'owner@wafa.test']))
            ->getJson('/api/v1/admin/auth/me');

        $response->assertForbidden();

        $this->assertSame('user_owner', $admin->fresh()->clerk_user_id);
    }

    public function test_an_added_account_does_not_link_without_an_email_claim(): void
    {
        $admin = AdminUser::factory()->unlinked()->create(['email' => 'staff@wafa.test']);

        $response = $this->withToken($this->clerkToken('user_staff'))->getJson('/api/v1/admin/auth/me');

        $response->assertForbidden();

        $this->assertNull($admin->fresh()->clerk_user_id);
    }

    public function test_a_deactivated_added_account_does_not_link(): void
    {
        $admin = AdminUser::factory()->unlinked()->create(['email' => 'former@wafa.test', 'is_active' => false]);

        $response = $this->withToken($this->clerkToken('user_former', ['email' => 'former@wafa.test']))
            ->getJson('/api/v1/admin/auth/me');

        $response->assertForbidden();

        $this->assertNull($admin->fresh()->clerk_user_id);
    }

    public function test_a_clerk_user_without_a_dashboard_account_gets_403(): void
    {
        // Merchants sign in to the same Clerk application, so a valid session
        // alone must not grant dashboard access.
        $response = $this->withToken($this->clerkToken('user_some_merchant'))->getJson('/api/v1/admin/auth/me');

        $response->assertForbidden()->assertJsonPath('message', 'This account does not have admin access.');
    }

    public function test_a_deactivated_dashboard_account_gets_403(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_former_staff', 'is_active' => false]);

        $response = $this->withToken($this->clerkToken('user_former_staff'))->getJson('/api/v1/admin/auth/me');

        $response->assertForbidden();
    }

    public function test_a_customer_token_gets_401(): void
    {
        $token = Customer::factory()->create()->createToken('phone', ['customer'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/auth/me');

        $response->assertUnauthorized();
    }
}
