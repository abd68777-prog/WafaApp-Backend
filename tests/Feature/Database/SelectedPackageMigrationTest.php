<?php

namespace Tests\Feature\Database;

use App\Models\Merchant;
use App\Models\Package;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shops that registered before the chosen package was stored get it back
 * from their latest period, or else from their latest payment.
 */
class SelectedPackageMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_shops_get_their_package_from_their_history(): void
    {
        $basic = Package::factory()->create();
        $plus = Package::factory()->create();

        $onTrial = Merchant::factory()->create();
        SubscriptionPeriod::factory()->trial()->for($onTrial)->create(['package_id' => $basic->id, 'starts_at' => now()->subMonth()]);
        SubscriptionPeriod::factory()->for($onTrial)->create(['package_id' => $plus->id]);

        $onlyPaid = Merchant::factory()->create(['status' => 'EXPIRED']);
        Payment::factory()->for($onlyPaid)->create(['package_id' => $basic->id]);

        $nothing = Merchant::factory()->create(['status' => 'EXPIRED']);

        Merchant::query()->update(['selected_package_id' => null]);
        $this->migration()->backfill();

        $this->assertSame($plus->id, $onTrial->fresh()->selected_package_id);
        $this->assertSame($basic->id, $onlyPaid->fresh()->selected_package_id);
        $this->assertNull($nothing->fresh()->selected_package_id);
    }

    /**
     * @return Migration&object{backfill: callable}
     */
    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_26_122909_add_selected_package_id_to_merchants_table.php');
    }
}
