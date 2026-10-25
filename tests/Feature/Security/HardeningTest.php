<?php

namespace Tests\Feature\Security;

use App\Enums\AdminRole;
use App\Enums\MerchantStatus;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\OtpCode;
use App\Models\Setting;
use App\Services\Merchant\PinUnlockToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The attacks a public API meets first: replayed codes and tokens, fields
 * the client should not control, files that are not what they claim, and
 * text meant to break a query.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+963933000111';

    public function test_a_code_signs_in_once_and_only_for_its_own_number(): void
    {
        OtpCode::factory()->create(['phone' => self::PHONE]);

        $this->postJson('/api/v1/customer/auth/verify', $this->verifyBody('0944000222'))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'OTP_EXPIRED');

        $this->postJson('/api/v1/customer/auth/verify', $this->verifyBody('0933000111'))->assertOk();

        $this->postJson('/api/v1/customer/auth/verify', $this->verifyBody('0933000111'))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'OTP_EXPIRED');
    }

    public function test_a_customer_token_dies_with_the_account_and_with_logout(): void
    {
        $customer = Customer::factory()->consented()->create();
        $token = $customer->createToken('phone', ['customer'])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/customer/me')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/customer/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $other = Customer::factory()->consented()->create()->createToken('phone', ['customer'])->plainTextToken;
        $this->withToken($other)->postJson('/api/v1/customer/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/v1/customer/me')->assertUnauthorized();
    }

    public function test_a_changed_pin_ends_the_pin_tokens_issued_before(): void
    {
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $oldToken = app(PinUnlockToken::class)->issue($merchant)['token'];

        $merchant->forceFill(['pin_hash' => Hash::make('9876')])->save();

        $this->withToken($this->clerkToken('user_shop'))
            ->withHeader('X-Pin-Token', $oldToken)
            ->getJson('/api/v1/merchant/stats')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PIN_REQUIRED');
    }

    public function test_fields_the_server_owns_cannot_be_set_by_the_client(): void
    {
        $customer = Customer::factory()->consented()->create(['phone' => self::PHONE]);
        $before = [$customer->phone, $customer->name, (string) $customer->registered_at, $customer->qr_secret];

        $this->withToken($customer->createToken('phone', ['customer'])->plainTextToken)
            ->patchJson('/api/v1/customer/me', [
                'campaigns_muted' => true,
                'phone' => '+963944000222',
                'name' => 'غيري',
                'registered_at' => null,
                'qr_secret' => 'AAAA',
            ])
            ->assertOk();

        $customer->refresh();
        $this->assertSame($before, [$customer->phone, $customer->name, (string) $customer->registered_at, $customer->qr_secret]);
        $this->assertTrue($customer->campaigns_muted);

        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $pin = app(PinUnlockToken::class)->issue($merchant)['token'];

        $this->withToken($this->clerkToken('user_shop'))->withHeader('X-Pin-Token', $pin)
            ->patchJson('/api/v1/merchant/profile', [
                'owner_name' => 'أحمد',
                'status' => 'ACTIVE',
                'clerk_user_id' => 'user_someone_else',
                'pin_hash' => 'x',
                'business_name' => 'اسم جديد',
                'suspended_at' => null,
            ])
            ->assertOk();

        $fresh = $merchant->fresh();
        $this->assertSame('أحمد', $fresh->owner_name);
        $this->assertSame(MerchantStatus::Trial, $fresh->status);
        $this->assertSame('user_shop', $fresh->clerk_user_id);
        $this->assertSame($merchant->pin_hash, $fresh->pin_hash);
        $this->assertSame($merchant->business_name, $fresh->business_name, 'renaming the business goes through the dashboard');
    }

    public function test_a_file_that_is_not_a_real_image_is_refused(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $merchant = Merchant::factory()->create(['clerk_user_id' => 'user_shop']);
        $pin = app(PinUnlockToken::class)->issue($merchant)['token'];

        // Real files on disk: a fake upload reports its type from the name,
        // while a real one is checked by its content.
        $files = [
            'svg, which can carry scripts' => [$this->realFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'format'],
            'php named .jpg' => [$this->realFile('logo.jpg', '<?php echo shell_exec($_GET["c"]); ?>'), 'format'],
            'html named .png' => [$this->realFile('logo.png', '<html><script>alert(1)</script></html>'), 'format'],
            'jpeg header followed by php' => [$this->realFile('logo.jpg', "\xFF\xD8\xFF\xE0<?php echo 1; ?>"), 'format'],
            'over 2 MB' => [UploadedFile::fake()->image('logo.png')->size(3000), 'max'],
        ];

        foreach ($files as $label => [$file, $rule]) {
            $this->withToken($this->clerkToken('user_shop'))->withHeader('X-Pin-Token', $pin)
                ->post('/api/v1/merchant/profile/logo', ['logo' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422, $label)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonPath('error.details.fields.logo', fn (array $rules) => in_array($rule, $rules, true));
        }

        $this->assertNull($merchant->fresh()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->withToken($this->clerkToken('user_shop'))->withHeader('X-Pin-Token', $pin)
            ->post('/api/v1/merchant/profile/logo', ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)], ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_text_meant_to_break_a_query_is_only_ever_searched_for(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        Merchant::factory()->create(['business_name' => 'كافيه الياسمين']);

        foreach (["' OR 1=1 --", '"; DROP TABLE merchants; --', '<script>alert(1)</script>', '\\\\', '%%'] as $term) {
            $this->withToken($this->clerkToken('user_admin'))
                ->getJson('/api/v1/admin/merchants?q='.urlencode($term))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }

        $this->assertSame(1, Merchant::query()->count());
    }

    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @return array<string, string>
     */
    private function verifyBody(string $phone): array
    {
        return ['phone' => $phone, 'code' => '123456', 'policy_version' => Setting::currentPolicyVersion()];
    }
}
