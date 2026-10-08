<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * A customer's session has no fixed length: it ends only after 90 days
 * without a single request, and each request starts the count again.
 */
class IdleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_unused_for_91_days_is_refused(): void
    {
        $token = $this->tokenLastUsedDaysAgo(91);

        $this->withToken($token)->getJson('/api/v1/customer/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_a_token_used_89_days_ago_still_works_and_its_count_starts_again(): void
    {
        $token = $this->tokenLastUsedDaysAgo(89);

        $this->withToken($token)->getJson('/api/v1/customer/me')->assertOk();

        $this->assertTrue(PersonalAccessToken::query()->sole()->last_used_at->isToday());
    }

    public function test_a_token_never_used_counts_from_when_it_was_issued(): void
    {
        $customer = Customer::factory()->create();
        $this->travel(-91)->days();
        $token = $customer->createToken('phone', ['customer'])->plainTextToken;
        $this->travelBack();

        $this->withToken($token)->getJson('/api/v1/customer/me')->assertUnauthorized();
    }

    public function test_the_idle_limit_comes_from_the_configuration(): void
    {
        config(['sanctum.customer_idle_days' => 180]);
        $token = $this->tokenLastUsedDaysAgo(120);

        $this->withToken($token)->getJson('/api/v1/customer/me')->assertOk();
    }

    public function test_pruning_deletes_only_idle_customer_tokens(): void
    {
        $this->tokenLastUsedDaysAgo(91);
        $this->tokenLastUsedDaysAgo(89);
        $merchantToken = (new PersonalAccessToken)->forceFill([
            'tokenable_type' => (new Merchant)->getMorphClass(),
            'tokenable_id' => Merchant::factory()->create()->id,
            'name' => 'other',
            'token' => hash('sha256', 'other'),
            'last_used_at' => now()->subYear(),
        ]);
        $merchantToken->save();

        $this->artisan('customer-tokens:prune-idle')->assertSuccessful();

        $remaining = PersonalAccessToken::query()->orderBy('id')->get();
        $this->assertCount(2, $remaining);
        $this->assertTrue($remaining[0]->last_used_at->isSameDay(now()->subDays(89)));
        $this->assertTrue($remaining[1]->is($merchantToken));
    }

    private function tokenLastUsedDaysAgo(int $days): string
    {
        $newToken = Customer::factory()->create()->createToken('phone', ['customer']);
        $newToken->accessToken->forceFill(['last_used_at' => now()->subDays($days)])->save();

        return $newToken->plainTextToken;
    }
}
