<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shops list with addresses and contact numbers: every shop working with
 * Wafa now, showing only the number it chose to publish.
 */
class ShopContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_shops_are_listed_with_their_published_number_only(): void
    {
        Merchant::factory()->create([
            'business_name' => 'كافيه الياسمين',
            'address' => 'شارع الحمرا',
            'phone' => '+963933111222',
            'contact_phone' => '+963112223344',
        ]);
        Merchant::factory()->active()->create(['business_name' => 'افران الشام', 'contact_phone' => null]);
        Merchant::factory()->grace()->create(['business_name' => 'حلويات']);
        Merchant::factory()->expired()->create();
        Merchant::factory()->suspended()->create();
        Merchant::factory()->awaitingPackage()->create();

        $this->asCustomer()->getJson('/api/v1/customer/shop-contacts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.business_name', 'افران الشام')
            ->assertJsonPath('data.0.contact_phone', null)
            ->assertJsonPath('data.2.business_name', 'كافيه الياسمين')
            ->assertJsonPath('data.2.address', 'شارع الحمرا')
            ->assertJsonPath('data.2.contact_phone', '+963112223344')
            ->assertJsonMissing(['phone' => '+963933111222'])
            ->assertJsonStructure(['data' => [['id', 'business_name', 'logo_url', 'business_type' => ['id', 'name'], 'governorate' => ['id', 'name'], 'address', 'contact_phone']]]);
    }

    public function test_an_unchanged_list_answers_304_and_signing_in_is_required(): void
    {
        Merchant::factory()->create();

        $etag = $this->asCustomer()->getJson('/api/v1/customer/shop-contacts')->assertOk()->headers->get('ETag');

        $this->asCustomer()->withHeader('If-None-Match', $etag)->getJson('/api/v1/customer/shop-contacts')->assertStatus(304);

        $this->app['auth']->forgetGuards();
        $this->withToken('nope')->getJson('/api/v1/customer/shop-contacts')->assertUnauthorized();
    }

    private function asCustomer(): self
    {
        $customer = Customer::factory()->consented()->create();

        return $this->withToken($customer->createToken('phone', ['customer'])->plainTextToken);
    }
}
