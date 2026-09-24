<?php

namespace Tests\Feature\Api\V1\Webhooks;

use App\Models\AdminUser;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Clerk (through Svix) tells us when a user changes their email or is
 * deleted. Only a correctly signed, recent delivery changes anything.
 */
class ClerkWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_KEY = 'webhook-signing-key-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.clerk.webhook_secret' => 'whsec_'.base64_encode(self::SECRET_KEY)]);
    }

    public function test_a_new_primary_email_reaches_the_shop_and_the_dashboard_account(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'old@shop.test']);
        $admin = AdminUser::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'old@shop.test']);

        $response = $this->deliver($this->userUpdated('user_1', 'New@Shop.test'));

        $response->assertOk();

        $this->assertSame('new@shop.test', $merchant->fresh()->email);
        $this->assertSame('new@shop.test', $admin->fresh()->email);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_user.email_synced',
            'subject_type' => 'admin',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_an_email_already_used_by_another_dashboard_account_is_left_alone(): void
    {
        $admin = AdminUser::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'mine@wafa.test']);
        AdminUser::factory()->unlinked()->create(['email' => 'invited@wafa.test']);

        $this->deliver($this->userUpdated('user_1', 'invited@wafa.test'))->assertOk();

        $this->assertSame('mine@wafa.test', $admin->fresh()->email);
    }

    public function test_a_deleted_user_unlinks_the_dashboard_account_so_it_can_link_again(): void
    {
        $admin = AdminUser::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'staff@wafa.test']);

        $this->deliver($this->userDeleted('user_1'))->assertOk();

        $this->assertNull($admin->fresh()->clerk_user_id);
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_user.unlinked', 'subject_id' => $admin->id]);
    }

    public function test_a_deleted_user_leaves_the_shop_intact_and_is_recorded_once(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_1']);

        $this->deliver($this->userDeleted('user_1'))->assertOk();
        $this->deliver($this->userDeleted('user_1'))->assertOk();

        $this->assertModelExists($merchant);
        $this->assertSame('user_1', $merchant->fresh()->clerk_user_id);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'merchant.clerk_user_deleted',
            'subject_type' => 'merchant',
            'subject_id' => $merchant->id,
        ]);
    }

    public function test_a_wrong_signature_is_refused_and_changes_nothing(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'old@shop.test']);
        $payload = json_encode($this->userUpdated('user_1', 'new@shop.test'));

        $response = $this->call('POST', '/api/v1/webhooks/clerk', server: $this->svixHeaders('msg_1', time(), 'v1,'.base64_encode('forged')), content: $payload);

        $response->assertStatus(400);

        $this->assertSame('old@shop.test', $merchant->fresh()->email);
    }

    public function test_an_old_delivery_is_refused(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'old@shop.test']);

        $response = $this->deliver($this->userUpdated('user_1', 'new@shop.test'), sentAt: time() - 600);

        $response->assertStatus(400);

        $this->assertSame('old@shop.test', $merchant->fresh()->email);
    }

    public function test_nothing_is_accepted_while_the_signing_secret_is_not_configured(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'old@shop.test']);
        $payload = $this->userUpdated('user_1', 'new@shop.test');
        $request = $this->deliverRequest($payload);
        config(['services.clerk.webhook_secret' => null]);

        $response = $this->call('POST', '/api/v1/webhooks/clerk', server: $request['server'], content: $request['content']);

        $response->assertStatus(400);

        $this->assertSame('old@shop.test', $merchant->fresh()->email);
    }

    public function test_an_event_we_do_not_handle_is_acknowledged(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_1', 'email' => 'old@shop.test']);

        $this->deliver(['type' => 'session.created', 'data' => ['id' => 'sess_1']])->assertOk();

        $this->assertSame('old@shop.test', $merchant->fresh()->email);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(array $payload, ?int $sentAt = null): TestResponse
    {
        $request = $this->deliverRequest($payload, $sentAt);

        return $this->call('POST', '/api/v1/webhooks/clerk', server: $request['server'], content: $request['content']);
    }

    /**
     * Signs the payload the way Svix does: HMAC-SHA256 over
     * `{id}.{timestamp}.{body}`, base64 encoded, prefixed with `v1,`.
     *
     * @param  array<string, mixed>  $payload
     * @return array{server: array<string, string>, content: string}
     */
    private function deliverRequest(array $payload, ?int $sentAt = null): array
    {
        $content = json_encode($payload);
        $timestamp = $sentAt ?? time();
        $signature = base64_encode(hash_hmac('sha256', "msg_1.{$timestamp}.{$content}", self::SECRET_KEY, true));

        return ['server' => $this->svixHeaders('msg_1', $timestamp, "v1,{$signature}"), 'content' => $content];
    }

    /**
     * @return array<string, string>
     */
    private function svixHeaders(string $id, int $timestamp, string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => $signature,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userUpdated(string $clerkUserId, string $primaryEmail): array
    {
        return [
            'type' => 'user.updated',
            'data' => [
                'id' => $clerkUserId,
                'primary_email_address_id' => 'idn_primary',
                'email_addresses' => [
                    ['id' => 'idn_other', 'email_address' => 'secondary@shop.test'],
                    ['id' => 'idn_primary', 'email_address' => $primaryEmail],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userDeleted(string $clerkUserId): array
    {
        return ['type' => 'user.deleted', 'data' => ['id' => $clerkUserId, 'deleted' => true, 'object' => 'user']];
    }
}
