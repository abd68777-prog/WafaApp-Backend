<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use App\Models\BusinessType;
use App\Models\Icon;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Business types and the icon library (requirements §5.5): added, renamed,
 * reordered and disabled — never deleted — by admins.
 */
class LookupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
    }

    public function test_an_admin_adds_and_disables_a_business_type_and_the_merchant_app_stops_offering_it(): void
    {
        $this->asAdmin()->postJson('/api/v1/admin/business-types', ['name' => 'حلويات', 'sort_order' => 3])
            ->assertCreated()
            ->assertJsonPath('data.name', 'حلويات')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.key');

        $sweets = BusinessType::query()->where('name', 'حلويات')->sole();

        $this->asAdmin()->patchJson("/api/v1/admin/business-types/{$sweets->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->asAdmin()->getJson('/api/v1/admin/business-types')->assertOk()->assertJsonPath('data.0.id', $sweets->id);

        $this->asAdmin()->postJson('/api/v1/admin/business-types', ['name' => 'حلويات'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['name' => ['taken']]);

        Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $this->withToken($this->clerkToken('user_shop'))->getJson('/api/v1/merchant/lookups')
            ->assertJsonMissing(['name' => 'حلويات']);
    }

    public function test_an_icon_key_is_set_once(): void
    {
        $this->asAdmin()->postJson('/api/v1/admin/icons', ['key' => 'coffee-cup', 'name' => 'فنجان'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'coffee-cup');

        $icon = Icon::query()->where('key', 'coffee-cup')->sole();

        $this->asAdmin()->patchJson("/api/v1/admin/icons/{$icon->id}", ['key' => 'tea', 'name' => 'فنجان قهوة'])
            ->assertOk()
            ->assertJsonPath('data.key', 'coffee-cup')
            ->assertJsonPath('data.name', 'فنجان قهوة');

        $this->asAdmin()->postJson('/api/v1/admin/icons', ['key' => 'Coffee Cup', 'name' => 'تاني'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['key' => ['format']]);
    }

    public function test_support_cannot_manage_the_lists(): void
    {
        AdminUser::factory()->support()->create(['clerk_user_id' => 'user_support']);

        $this->withToken($this->clerkToken('user_support'))->getJson('/api/v1/admin/icons')->assertForbidden();
    }

    private function asAdmin(): self
    {
        return $this->withToken($this->clerkToken('user_admin'));
    }
}
