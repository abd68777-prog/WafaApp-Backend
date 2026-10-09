<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Enums\CardStatus;
use App\Enums\StampMethod;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\Card;
use App\Models\CardCycle;
use App\Models\Governorate;
use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Stamp;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The shops screen (requirements §5.3): a filtered list, the shop's page, and
 * correcting the business name or type with a reason on the record.
 */
class AdminMerchantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 12:00:00');
        Storage::fake();
        AdminUser::factory()->create(['clerk_user_id' => 'user_support', 'role' => AdminRole::Support]);
    }

    public function test_the_list_filters_by_status_place_type_package_registration_and_search(): void
    {
        $damascus = Governorate::factory()->create();
        $cafes = BusinessType::factory()->create();
        $gold = Package::factory()->create(['name' => 'ذهبية']);

        $yasmin = Merchant::factory()->active()->create([
            'business_name' => 'كافيه الياسمين',
            'governorate_id' => $damascus->id,
            'business_type_id' => $cafes->id,
            'phone' => '+963933111222',
        ]);
        SubscriptionPeriod::factory()->for($yasmin)->for($gold)->create();
        $sham = Merchant::factory()->onPackage()->create(['business_name' => 'افران الشام', 'email' => 'sham@example.com']);
        Merchant::factory()->expired()->create();
        $registering = Merchant::factory()->awaitingPackage()->create();

        $this->list()->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $registering->id)
            ->assertJsonPath('data.0.status', null)
            ->assertJsonPath('data.0.registration_step', 'package')
            ->assertJsonPath('data.0.package', null)
            ->assertJsonPath('data.1.registration_step', 'done');
        $this->list(['registration_step' => 'package'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $registering->id);
        $this->list(['registration_step' => 'done'])->assertJsonCount(3, 'data');

        $this->list(['status' => 'ACTIVE'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $yasmin->id);
        $this->list(['governorate_id' => $damascus->id])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $yasmin->id);
        $this->list(['business_type_id' => $cafes->id])->assertJsonCount(1, 'data');
        $this->list(['package_id' => $gold->id])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.package.name', 'ذهبية')
            ->assertJsonPath('data.0.phone', '+963933111222')
            ->assertJsonPath('data.0.subscription_ends_at', '2026-11-01T12:00:00Z');
        $this->list(['q' => 'الياسمين'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $yasmin->id);
        $this->list(['q' => 'sham@'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sham->id);
        $this->list(['q' => '0933111222'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $yasmin->id);
        $this->list(['status' => 'WHATEVER'])->assertUnprocessable();
    }

    public function test_the_ready_filter_finds_trials_ending_within_a_week(): void
    {
        $endingSoon = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial(5)->for($endingSoon)->create();
        $later = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial(10)->for($later)->create();

        $this->list(['trial_ending' => 'week'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $endingSoon->id);
    }

    public function test_the_shop_page_shows_its_subscription_cards_payments_and_totals(): void
    {
        $merchant = Merchant::factory()->onPackage()->create(['address' => 'شارع الحمرا']);
        $card = Card::factory()->for($merchant)->create();
        Card::factory()->for($merchant)->suspended()->create();
        $cycle = CardCycle::factory()->for($card)->create(['stamps_count' => 2]);
        CardCycle::factory()->for($card)->redeemed()->create();
        Stamp::factory()->for($cycle)->create();
        Stamp::factory()->for($cycle)->create(['method' => StampMethod::Phone]);
        Stamp::factory()->for($cycle)->cancelled()->create();
        Payment::factory()->for($merchant)->create();

        $this->withToken($this->clerkToken('user_support'))
            ->getJson("/api/v1/admin/merchants/{$merchant->id}")
            ->assertOk()
            ->assertJsonPath('data.address', 'شارع الحمرا')
            ->assertJsonPath('data.registered_at', '2026-10-01T12:00:00Z')
            ->assertJsonPath('data.subscription.status', 'TRIAL')
            ->assertJsonCount(1, 'data.periods')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonCount(2, 'data.cards')
            ->assertJsonPath('data.cards.0.customers_count', 2)
            ->assertJsonPath('data.cards.1.status', 'suspended')
            ->assertJsonPath('data.stats.customers_total', 2)
            ->assertJsonPath('data.stats.stamps_total', 2)
            ->assertJsonPath('data.stats.stamps_via_phone_total', 1)
            ->assertJsonPath('data.stats.rewards_redeemed_total', 1);
    }

    public function test_support_corrects_the_business_name_and_type_with_a_reason(): void
    {
        $merchant = Merchant::factory()->create(['business_name' => 'كافيه الياسمن']);
        $bakery = BusinessType::factory()->create();

        $this->withToken($this->clerkToken('user_support'))
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}", [
                'business_name' => 'كافيه الياسمين',
                'business_type_id' => $bakery->id,
                'reason' => 'خطأ إملائي بالتسجيل',
            ])
            ->assertOk()
            ->assertJsonPath('data.business_name', 'كافيه الياسمين')
            ->assertJsonPath('data.business_type.id', $bakery->id);

        $log = AuditLog::query()->sole();
        $this->assertSame('merchant.identity_updated', $log->action);
        $this->assertSame('كافيه الياسمن', $log->before['business_name']);
        $this->assertSame('خطأ إملائي بالتسجيل', $log->after['reason']);

        $this->withToken($this->clerkToken('user_support'))
            ->patchJson("/api/v1/admin/merchants/{$merchant->id}", ['business_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields', ['business_name' => ['min'], 'reason' => ['required']]);
    }

    public function test_the_payments_reviewer_does_not_see_shops(): void
    {
        AdminUser::factory()->paymentsReviewer()->create(['clerk_user_id' => 'user_reviewer']);

        $this->withToken($this->clerkToken('user_reviewer'))->getJson('/api/v1/admin/merchants')->assertForbidden();
    }

    public function test_an_admin_takes_down_an_unsuitable_card_once(): void
    {
        AdminUser::factory()->create(['clerk_user_id' => 'user_admin', 'role' => AdminRole::Admin]);
        $card = Card::factory()->for(Merchant::factory()->create())->create();

        foreach ([1, 2] as $attempt) {
            $this->withToken($this->clerkToken('user_admin'))
                ->postJson("/api/v1/admin/cards/{$card->id}/suspend", ['reason' => 'محتوى غير مناسب'])
                ->assertOk()
                ->assertJsonPath('data.status', 'suspended');
        }

        $this->assertSame(CardStatus::Suspended, $card->fresh()->status);
        $this->assertSame('card.suspended', AuditLog::query()->sole()->action);

        $this->withToken($this->clerkToken('user_support'))
            ->postJson("/api/v1/admin/cards/{$card->id}/suspend", ['reason' => 'محتوى غير مناسب'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function list(array $query = []): TestResponse
    {
        return $this->withToken($this->clerkToken('user_support'))
            ->getJson('/api/v1/admin/merchants?'.http_build_query($query));
    }
}
