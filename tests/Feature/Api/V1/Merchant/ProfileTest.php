<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Models\Merchant;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The shop's own details (contract §5.10): the owner's name, phone, address
 * and logo. The business name, type and governorate stay with support.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private const CLERK_USER = 'user_shop';

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->merchant = Merchant::factory()->create([
            'clerk_user_id' => self::CLERK_USER,
            'business_name' => 'كافيه الياسمين',
            'address' => 'شارع الحمرا',
        ]);
    }

    public function test_the_owner_name_phone_and_address_change_and_nothing_else(): void
    {
        $this->asMerchant()->patchJson('/api/v1/merchant/profile', [
            'owner_name' => 'أحمد',
            'phone' => '0944111222',
            'address' => null,
            'business_name' => 'اسم تاني',
        ])
            ->assertOk()
            ->assertJsonPath('data.owner_name', 'أحمد')
            ->assertJsonPath('data.phone', '+963944111222')
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.business_name', 'كافيه الياسمين');

        $this->asMerchant()->patchJson('/api/v1/merchant/profile', ['phone' => '+963944111222'])->assertOk();
    }

    public function test_a_phone_another_shop_uses_is_taken(): void
    {
        Merchant::factory()->create(['phone' => '+963955000111']);

        $this->asMerchant()->patchJson('/api/v1/merchant/profile', ['phone' => '0955000111', 'owner_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['owner_name' => ['min'], 'phone' => ['taken']]);
    }

    public function test_a_new_logo_replaces_the_old_file(): void
    {
        $this->merchant->forceFill(['logo_path' => UploadedFile::fake()->image('old.png')->store('merchants/logos', 'public')])->save();
        $old = $this->merchant->logo_path;

        $response = $this->asMerchant()->post('/api/v1/merchant/profile/logo', [
            'logo' => UploadedFile::fake()->image('logo.webp'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.logo_url', fn (?string $url): bool => $url !== null && str_contains($url, 'merchants/logos'));
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($this->merchant->fresh()->logo_path);

        $this->asMerchant()->post('/api/v1/merchant/profile/logo', [
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_the_profile_is_behind_the_pin(): void
    {
        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->patchJson('/api/v1/merchant/profile', ['owner_name' => 'أحمد'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    private function asMerchant(): self
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token']);
    }
}
