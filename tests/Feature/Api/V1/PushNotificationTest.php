<?php

namespace Tests\Feature\Api\V1;

use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Notifications\StampAdded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Every inbox notification is also pushed through FCM to all the account's
 * devices, from the queue, and tokens FCM no longer knows are forgotten.
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private CardCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();

        config(['firebase.projects.app.credentials' => 'firebase-credentials.json']);
        $this->customer = Customer::factory()->create();
        $this->cycle = CardCycle::factory()->for($this->customer)->create(['stamps_count' => 1]);
    }

    public function test_the_push_repeats_the_inbox_entry_on_every_device_with_string_data(): void
    {
        $this->deviceOf($this->customer, 'phone-token');
        $this->deviceOf($this->customer, 'tablet-token');

        $this->expectPush(function (CloudMessage $message, array $tokens): bool {
            $payload = $message->jsonSerialize();
            $entry = $this->customer->notifications()->sole();

            $this->assertSame(['phone-token', 'tablet-token'], $tokens);
            $this->assertSame($entry->data['title'], $payload['notification']['title']);
            $this->assertSame($entry->data['body'], $payload['notification']['body']);
            $this->assertSame([
                'type' => 'stamp_added',
                'notification_id' => $entry->id,
                'merchant_id' => (string) $this->cycle->card->merchant_id,
                'card_id' => (string) $this->cycle->card_id,
                'cycle_id' => (string) $this->cycle->id,
            ], $payload['data']);

            return true;
        });

        $this->customer->notify(new StampAdded($this->cycle, $this->cycle->card));
    }

    public function test_tokens_fcm_reports_as_unregistered_are_deleted(): void
    {
        $this->deviceOf($this->customer, 'live-token');
        $this->deviceOf($this->customer, 'uninstalled-token');

        $this->expectPush(fn (): bool => true, MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'live-token'), []),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'uninstalled-token'), NotFound::becauseTokenNotFound('uninstalled-token')),
        ]));

        $this->customer->notify(new StampAdded($this->cycle, $this->cycle->card));

        $this->assertSame(['live-token'], DeviceToken::query()->pluck('token')->all());
    }

    public function test_the_inbox_is_written_at_once_while_the_push_waits_in_the_queue(): void
    {
        config(['queue.default' => 'database']);
        $this->deviceOf($this->customer, 'phone-token');
        $this->mock(Messaging::class, fn (MockInterface $mock) => $mock->shouldNotReceive('sendMulticast'));

        $this->customer->notify(new StampAdded($this->cycle, $this->cycle->card));

        $this->assertSame(1, $this->customer->notifications()->count());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_nothing_is_pushed_without_firebase_credentials(): void
    {
        config(['firebase.projects.app.credentials' => null]);
        $this->deviceOf($this->customer, 'phone-token');
        $this->mock(Messaging::class, fn (MockInterface $mock) => $mock->shouldNotReceive('sendMulticast'));

        $this->customer->notify(new StampAdded($this->cycle, $this->cycle->card));

        $this->assertSame(1, $this->customer->notifications()->count());
    }

    public function test_push_test_sends_one_message_to_the_given_token(): void
    {
        $this->mock(Messaging::class, fn (MockInterface $mock) => $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn (CloudMessage $message): bool => $message->jsonSerialize()['token'] === 'phone-token')
            ->andReturn(['name' => 'projects/wafa/messages/1']));

        $this->artisan('push:test', ['token' => 'phone-token'])
            ->expectsOutput('Sent: projects/wafa/messages/1')
            ->assertSuccessful();
    }

    public function test_push_test_explains_a_refused_token_and_a_missing_configuration(): void
    {
        $this->mock(Messaging::class, fn (MockInterface $mock) => $mock->shouldReceive('send')
            ->andThrow(NotFound::becauseTokenNotFound('old-token')));

        $this->artisan('push:test', ['token' => 'old-token'])->assertFailed();

        config(['firebase.projects.app.credentials' => null]);
        $this->artisan('push:test', ['token' => 'old-token'])
            ->expectsOutputToContain('FIREBASE_CREDENTIALS')
            ->assertFailed();
    }

    /**
     * @param  callable(CloudMessage, list<string>): bool  $check
     */
    private function expectPush(callable $check, ?MulticastSendReport $report = null): void
    {
        $this->mock(Messaging::class, fn (MockInterface $mock) => $mock->shouldReceive('sendMulticast')
            ->once()
            ->withArgs($check)
            ->andReturn($report ?? MulticastSendReport::withItems([])));
    }

    private function deviceOf(Customer $customer, string $token): void
    {
        DeviceToken::factory()->create(['owner_id' => $customer->id, 'token' => $token]);
    }
}
