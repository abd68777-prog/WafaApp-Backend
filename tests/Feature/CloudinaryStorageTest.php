<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Logos and payment proofs on Cloudinary: signed uploads and deletes through
 * its REST API, and CDN links back.
 */
class CloudinaryStorageTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '111122223333444';

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 12:00:00');
        config(['filesystems.disks.cloudinary.cloudinary_url' => 'cloudinary://'.self::KEY.':'.self::SECRET.'@democloud']);
        Storage::forgetDisk('cloudinary');
    }

    public function test_an_upload_is_signed_and_linked_from_the_cdn(): void
    {
        Http::fake(['api.cloudinary.com/*' => Http::response(['public_id' => 'merchants/logos/abc'])]);

        $this->assertTrue(Storage::disk('cloudinary')->put('merchants/logos/abc.webp', 'image-bytes'));

        Http::assertSent(function (Request $request): bool {
            $fields = collect($request->data())->mapWithKeys(fn (array $part): array => [$part['name'] => $part['contents']]);
            $timestamp = (string) now()->getTimestamp();

            return $request->url() === 'https://api.cloudinary.com/v1_1/democloud/image/upload'
                && $fields['public_id'] === 'merchants/logos/abc'
                && $fields['api_key'] === self::KEY
                && $fields['timestamp'] === $timestamp
                && $fields['signature'] === sha1('public_id=merchants/logos/abc&timestamp='.$timestamp.self::SECRET)
                && $fields['file'] === 'image-bytes';
        });

        $this->assertSame('https://res.cloudinary.com/democloud/image/upload/merchants/logos/abc.webp', Storage::disk('cloudinary')->url('merchants/logos/abc.webp'));
        $this->assertSame(
            'https://res.cloudinary.com/democloud/image/upload/payments/proofs/x.png',
            Storage::disk('cloudinary')->temporaryUrl('payments/proofs/x.png', now()->addMinutes(30)),
        );
    }

    public function test_a_delete_is_signed_and_existence_is_checked(): void
    {
        Http::fake([
            'api.cloudinary.com/v1_1/democloud/image/destroy' => Http::response(['result' => 'ok']),
            'api.cloudinary.com/v1_1/democloud/resources/image/upload/merchants/logos/here' => Http::response(['public_id' => 'merchants/logos/here']),
            'api.cloudinary.com/v1_1/democloud/resources/image/upload/*' => Http::response(['error' => ['message' => 'Not found']], 404),
        ]);

        $this->assertTrue(Storage::disk('cloudinary')->delete('merchants/logos/old.png'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudinary.com/v1_1/democloud/image/destroy'
            && $request['public_id'] === 'merchants/logos/old'
            && $request['invalidate'] === 'true'
            && $request['signature'] === sha1('invalidate=true&public_id=merchants/logos/old&timestamp='.now()->getTimestamp().self::SECRET));

        $this->assertTrue(Storage::disk('cloudinary')->exists('merchants/logos/here.png'));
        $this->assertFalse(Storage::disk('cloudinary')->exists('merchants/logos/gone.png'));
    }

    public function test_a_failed_upload_is_reported_not_saved(): void
    {
        Http::fake(['api.cloudinary.com/*' => Http::response(['error' => ['message' => 'Invalid Signature']], 401)]);

        $this->assertFalse(Storage::disk('cloudinary')->put('merchants/logos/abc.webp', 'image-bytes'));
    }

    public function test_a_malformed_cloudinary_url_is_refused(): void
    {
        config(['filesystems.disks.cloudinary.cloudinary_url' => 'cloudinary://<your_api_key>@democloud']);
        Storage::forgetDisk('cloudinary');

        $this->expectException(InvalidArgumentException::class);

        Storage::disk('cloudinary');
    }

    public function test_a_shop_logo_goes_to_cloudinary_when_it_is_the_media_disk(): void
    {
        config(['filesystems.media_disk' => 'cloudinary']);
        Http::fake(['api.cloudinary.com/*' => Http::response(['public_id' => 'x'])]);
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);

        $this->withToken($this->clerkToken('user_shop'))
            ->withHeader('X-Pin-Token', app(PinUnlockToken::class)->issue($merchant)['token'])
            ->post('/api/v1/merchant/profile/logo', ['logo' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn (string $url): bool => str_starts_with($url, 'https://res.cloudinary.com/democloud/image/upload/merchants/logos/'));

        Http::assertSentCount(1);
    }
}
