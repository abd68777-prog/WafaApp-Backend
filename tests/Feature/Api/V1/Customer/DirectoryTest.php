<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantMute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop directory and a shop's page (contract §4.4): only shops a customer
 * would get a stamp at, all in one response with an ETag.
 */
class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->consented()->create();
    }

    public function test_the_directory_lists_shops_that_take_stamps_with_an_active_card(): void
    {
        $cafe = Merchant::factory()->create(['business_name' => 'كافيه الياسمين', 'address' => 'شارع الحمرا']);
        Card::factory()->for($cafe)->count(2)->create();
        Card::factory()->for($cafe)->create(['status' => CardStatus::Suspended]);
        $bakery = Merchant::factory()->active()->create(['business_name' => 'افران الشام']);
        Card::factory()->for($bakery)->create();
        Card::factory()->for(Merchant::factory()->expired()->create())->create();
        Merchant::factory()->create();
        Card::factory()->for(Merchant::factory()->create())->create(['status' => CardStatus::Suspended]);

        $this->asCustomer()->getJson('/api/v1/customer/directory')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.business_name', 'افران الشام')
            ->assertJsonPath('data.1.id', $cafe->id)
            ->assertJsonPath('data.1.address', 'شارع الحمرا')
            ->assertJsonPath('data.1.active_cards_count', 2)
            ->assertJsonStructure(['data' => [['id', 'business_name', 'logo_url', 'business_type' => ['id', 'name'], 'governorate' => ['id', 'name'], 'address', 'active_cards_count']]]);
    }

    public function test_an_unchanged_directory_answers_304_to_its_etag(): void
    {
        Card::factory()->for(Merchant::factory()->create())->create();

        $first = $this->asCustomer()->getJson('/api/v1/customer/directory')->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->asCustomer()->withHeader('If-None-Match', $etag)->getJson('/api/v1/customer/directory')
            ->assertStatus(304);

        Card::factory()->for(Merchant::factory()->create())->create();

        $this->asCustomer()->withHeader('If-None-Match', $etag)->getJson('/api/v1/customer/directory')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_shop_page_shows_its_active_cards_and_whether_it_is_muted(): void
    {
        $shop = Merchant::factory()->create(['address' => 'شارع الحمرا']);
        $card = Card::factory()->for($shop)->create();
        Card::factory()->for($shop)->create(['status' => CardStatus::Suspended]);
        MerchantMute::factory()->create(['customer_id' => $this->customer->id, 'merchant_id' => $shop->id]);

        $this->asCustomer()->getJson("/api/v1/customer/merchants/{$shop->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $shop->id)
            ->assertJsonPath('data.address', 'شارع الحمرا')
            ->assertJsonPath('data.accepting_stamps', true)
            ->assertJsonPath('data.muted', true)
            ->assertJsonCount(1, 'data.cards')
            ->assertJsonPath('data.cards.0.id', $card->id);
    }

    public function test_a_shop_outside_the_directory_opens_only_for_its_own_customers_and_without_cards(): void
    {
        $expired = Merchant::factory()->expired()->create();
        $card = Card::factory()->for($expired)->create();

        $this->asCustomer()->getJson("/api/v1/customer/merchants/{$expired->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        CardCycle::factory()->for($card)->for($this->customer)->create();

        $this->asCustomer()->getJson("/api/v1/customer/merchants/{$expired->id}")
            ->assertOk()
            ->assertJsonPath('data.accepting_stamps', false)
            ->assertJsonPath('data.cards', []);
    }

    public function test_the_directory_needs_a_finished_profile(): void
    {
        $incomplete = Customer::factory()->withoutProfile()->consented()->create();

        $this->withToken($incomplete->createToken('phone', ['customer'])->plainTextToken)
            ->getJson('/api/v1/customer/directory')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PROFILE_INCOMPLETE');
    }

    private function asCustomer(): self
    {
        return $this->withToken($this->customer->createToken('phone', ['customer'])->plainTextToken);
    }
}
