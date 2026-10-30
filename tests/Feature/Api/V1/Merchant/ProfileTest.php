<?php

namespace Tests\Feature\Api\V1\Merchant;

use App\Models\Merchant;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

    public function test_the_shop_publishes_a_mobile_or_landline_contact_number(): void
    {
        $this->asMerchant()->patchJson('/api/v1/merchant/profile', ['contact_phone' => '011 222 3344'])
            ->assertOk()
            ->assertJsonPath('data.contact_phone', '+963112223344');

        $this->asMerchant()->patchJson('/api/v1/merchant/profile', ['contact_phone' => '0944-111-222'])
            ->assertJsonPath('data.contact_phone', '+963944111222');

        $this->asMerchant()->patchJson('/api/v1/merchant/profile', ['contact_phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['contact_phone' => ['format']]);

        $this->asMerchant()->patchJson('/api/v1/merchant/profile', ['contact_phone' => null])
            ->assertOk()
            ->assertJsonPath('data.contact_phone', null);
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

    public function test_a_wide_photo_becomes_a_1200_by_600_webp_cover_and_replaces_the_old_one(): void
    {
        $this->merchant->forceFill(['cover_path' => 'merchants/covers/old.webp'])->save();
        Storage::disk('public')->put('merchants/covers/old.webp', 'old');

        $response = $this->uploadCover(UploadedFile::fake()->image('cover.jpg', 3000, 1000));

        $path = $this->merchant->fresh()->cover_path;
        $response->assertOk()->assertJsonPath('data.cover_url', Storage::disk('public')->url($path));
        $this->assertStringStartsWith('merchants/covers/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertMissing('merchants/covers/old.webp');

        $this->assertStoredCover($path, 1200, 600);
    }

    public function test_a_small_portrait_photo_is_cropped_to_2_1_without_being_enlarged(): void
    {
        $this->uploadCover(UploadedFile::fake()->image('cover.png', 400, 900))->assertOk();

        $this->assertStoredCover($this->merchant->fresh()->cover_path, 400, 200);
    }

    public function test_nothing_appended_to_the_photo_survives_into_the_cover(): void
    {
        $photo = UploadedFile::fake()->image('cover.jpg', 800, 400);
        file_put_contents($photo->getRealPath(), '<?php echo 1; ?>', FILE_APPEND);

        $this->uploadCover($photo)->assertOk();

        $stored = Storage::disk('public')->get($this->merchant->fresh()->cover_path);
        $this->assertStringNotContainsString('<?php', $stored);
    }

    public function test_a_cover_must_be_an_image_of_at_most_5_mb(): void
    {
        $this->assertCoverRefused(UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'));
        $this->assertCoverRefused(UploadedFile::fake()->image('cover.jpg', 800, 400)->size(5121));
    }

    public function test_a_script_dressed_as_a_jpeg_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($path, "\xFF\xD8\xFF\xE0<?php echo 1; ?>");

        $this->assertCoverRefused(new UploadedFile($path, 'cover.jpg', null, null, true));
    }

    public function test_the_shop_removes_its_cover(): void
    {
        $this->uploadCover(UploadedFile::fake()->image('cover.jpg', 800, 400))->assertOk();
        $path = $this->merchant->fresh()->cover_path;

        foreach ([1, 2] as $attempt) {
            $this->asMerchant()->deleteJson('/api/v1/merchant/profile/cover')
                ->assertOk()
                ->assertJsonPath('data.cover_url', null);
        }

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_profile_is_behind_the_pin(): void
    {
        $this->withToken($this->clerkToken(self::CLERK_USER))
            ->patchJson('/api/v1/merchant/profile', ['owner_name' => 'أحمد'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    private function uploadCover(UploadedFile $file): TestResponse
    {
        return $this->asMerchant()->post('/api/v1/merchant/profile/cover', ['cover' => $file], ['Accept' => 'application/json']);
    }

    private function assertCoverRefused(UploadedFile $file): void
    {
        $this->uploadCover($file)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['cover']]]]);

        $this->assertNull($this->merchant->fresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles('merchants/covers'));
    }

    private function assertStoredCover(string $path, int $width, int $height): void
    {
        $size = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([$width, $height, 'image/webp'], [$size[0], $size[1], $size['mime']]);
    }

    private function asMerchant(): self
    {
        return $this->withToken($this->clerkToken(self::CLERK_USER))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($this->merchant)['token']);
    }
}
