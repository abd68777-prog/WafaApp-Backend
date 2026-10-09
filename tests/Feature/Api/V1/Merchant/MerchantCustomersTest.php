<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Stamp;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The shop's customers (contract §5.6): most recently active here first,
 * pending numbers included, searched by name, the number always masked.
 */
class MerchantCustomersTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()->create(['clerk_user_id' => self::CLERK_USER]);
        $this->card = Card::factory()->for($this->merchant)->create(['name' => 'القهوة', 'stamps_required' => 5]);
    }

    public function test_customers_come_by_their_last_activity_here_with_their_open_cards(): void
    {
        $sara = Customer::factory()->create(['name' => 'سارة', 'phone' => '+963933123456']);
        $pending = Customer::factory()->pending()->create(['phone' => '+963944000111']);
        $omar = Customer::factory()->create(['name' => 'عمر']);

        $saraCycle = $this->cycle($sara, ['stamps_count' => 3, 'updated_at' => now()->subDays(1)]);
        $this->cycle($sara, ['updated_at' => now()->subDays(9)], redeemed: true);
        $this->cycle($pending, ['updated_at' => now()->subHours(2)]);
        $this->cycle($omar, ['updated_at' => now()->subDays(5)]);
        // Omar is busy at another shop today: it does not move him up here.
        CardCycle::factory()->for($omar)->create(['updated_at' => now()]);
        Stamp::factory()->for($saraCycle)->create([
            'card_id' => $this->card->id, 'customer_id' => $sara->id, 'merchant_id' => $this->merchant->id,
            'stamped_at' => '2026-10-06 09:00:00',
        ]);

        $response = $this->customers('')->assertOk();

        $this->assertSame([$pending->id, $sara->id, $omar->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.name', null)
            ->assertJsonPath('data.0.registered', false)
            ->assertJsonPath('data.0.phone_masked', '0944***111')
            ->assertJsonPath('data.1.phone_masked', '0933***456')
            ->assertJsonPath('data.1.last_stamp_at', '2026-10-06T09:00:00Z')
            ->assertJsonPath('data.1.cards', [[
                'card_id' => $this->card->id,
                'card_name' => 'القهوة',
                'stamps_count' => 3,
                'stamps_required' => 5,
                'status' => 'COLLECTING',
            ]])
            ->assertJsonPath('data.2.last_stamp_at', null);
    }

    public function test_search_by_name_and_pages(): void
    {
        foreach (['سارة', 'سامر', 'عمر'] as $i => $name) {
            $this->cycle(Customer::factory()->create(['name' => $name]), ['updated_at' => now()->subHours($i)]);
        }

        $this->customers('?q=سا')->assertJsonCount(2, 'data');

        $first = $this->customers('?limit=2')->assertJsonCount(2, 'data');
        $this->customers('?limit=2&cursor='.$first->json('meta.next_cursor'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'عمر')
            ->assertJsonPath('meta.next_cursor', null);

        $this->customers('?q=س')->assertUnprocessable()->assertJsonPath('error.details.fields.q', ['min']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function cycle(Customer $customer, array $attributes, bool $redeemed = false): CardCycle
    {
        $factory = CardCycle::factory()->for($this->card)->for($customer);

        return ($redeemed ? $factory->redeemed() : $factory)->create($attributes);
    }

    private function customers(string $query): TestResponse
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token'])
            ->getJson('/api/v1/merchant/customers'.$query);
    }
}
