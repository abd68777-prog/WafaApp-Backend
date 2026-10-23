<?php

namespace Tests\Feature\Console;

use App\Enums\DeletionSource;
use App\Enums\DeletionSubjectType;
use App\Enums\MerchantStatus;
use App\Models\CardCycle;
use App\Models\Customer;
use App\Models\DeletionRequest;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Stamp;
use App\Models\SubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What the privacy policy promises to forget on its own (§3 and §10).
 */
class PrivacyPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_number_that_never_signed_up_goes_with_its_stamps_after_twelve_months(): void
    {
        $this->travelTo('2026-10-01 12:00:00');

        $forgotten = Customer::factory()->pending()->create(['last_activity_at' => '2025-09-30 12:00:00']);
        $cycle = CardCycle::factory()->create(['customer_id' => $forgotten->id, 'stamps_count' => 1]);
        Stamp::factory()->create(['card_cycle_id' => $cycle->id, 'card_id' => $cycle->card_id, 'customer_id' => $forgotten->id, 'merchant_id' => $cycle->merchant_id]);

        $recent = Customer::factory()->pending()->create(['last_activity_at' => '2025-10-02 12:00:00']);
        $registered = Customer::factory()->create(['last_activity_at' => '2024-01-01 12:00:00']);

        $this->artisan('customers:purge-pending')->assertSuccessful();

        $this->assertNull(Customer::withTrashed()->find($forgotten->id));
        $this->assertSame(0, Stamp::query()->where('customer_id', $forgotten->id)->count());
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull($registered->fresh());
    }

    public function test_a_deleted_shops_billing_records_are_kept_two_years(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-10-01 12:00:00');

        $old = $this->deletedShopWithPayment('2024-09-30 12:00:00');
        $recent = $this->deletedShopWithPayment('2024-10-02 12:00:00');

        $this->artisan('billing:purge-records')->assertSuccessful();

        $this->assertSame(0, Payment::query()->where('merchant_id', $old['merchant']->id)->count());
        $this->assertSame(0, SubscriptionPeriod::query()->where('merchant_id', $old['merchant']->id)->count());
        Storage::disk('local')->assertMissing($old['payment']->proof_path);

        $this->assertSame(1, Payment::query()->where('merchant_id', $recent['merchant']->id)->count());
        Storage::disk('local')->assertExists($recent['payment']->proof_path);
        $this->assertNotNull($old['merchant']->fresh(), 'the business name stays');
    }

    /**
     * @return array{merchant: Merchant, payment: Payment}
     */
    private function deletedShopWithPayment(string $executedAt): array
    {
        $merchant = Merchant::factory()->create(['status' => MerchantStatus::Deleted]);
        $period = SubscriptionPeriod::factory()->for($merchant)->create();
        $payment = Payment::factory()->approved()->create(['merchant_id' => $merchant->id, 'subscription_period_id' => $period->id]);
        Storage::disk('local')->put($payment->proof_path, 'receipt');

        $deletionRequest = new DeletionRequest([
            'subject_type' => DeletionSubjectType::Merchant,
            'subject_id' => $merchant->id,
            'source' => DeletionSource::Support,
            'requested_at' => $executedAt,
        ]);
        $deletionRequest->forceFill(['executed_at' => $executedAt])->save();

        return ['merchant' => $merchant, 'payment' => $payment];
    }
}
