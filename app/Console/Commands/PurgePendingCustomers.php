<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\OtpCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A phone number a shop stamped that never signed up is deleted, with its
 * stamps, twelve months after its last activity (privacy policy §3 and §5;
 * requirements §3.8).
 */
#[Signature('customers:purge-pending')]
#[Description('Delete phone-only customers with no activity for 12 months, with their stamps')]
class PurgePendingCustomers extends Command
{
    public const MONTHS = 12;

    public function handle(): int
    {
        $deleted = 0;

        Customer::withTrashed()
            ->whereNull('registered_at')
            ->whereNotNull('phone')
            ->whereRaw('coalesce(last_activity_at, created_at) < ?', [now()->subMonths(self::MONTHS)])
            ->chunkById(200, function ($customers) use (&$deleted): void {
                foreach ($customers as $customer) {
                    DB::transaction(function () use ($customer): void {
                        OtpCode::query()->where('phone', $customer->phone)->delete();
                        $customer->notifications()->delete();
                        // Cycles and stamps go with the row.
                        $customer->forceDelete();
                    });
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} pending customer(s).");

        return self::SUCCESS;
    }
}
