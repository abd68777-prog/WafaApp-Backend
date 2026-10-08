<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Deletes customer tokens left unused for longer than the idle limit. They
 * are already refused at sign-in time; this only keeps the table small.
 * `sanctum:prune-expired` cannot do it: it looks at `expires_at`, which
 * customer tokens never set.
 */
#[Signature('customer-tokens:prune-idle')]
#[Description('Delete customer tokens unused for longer than CUSTOMER_TOKEN_IDLE_DAYS')]
class PruneIdleCustomerTokens extends Command
{
    public function handle(): int
    {
        $deleted = PersonalAccessToken::query()
            ->where('tokenable_type', (new Customer)->getMorphClass())
            ->whereRaw('coalesce(last_used_at, created_at) < ?', [Customer::tokenIdleCutoff()])
            ->delete();

        $this->info("Deleted {$deleted} idle customer token(s).");

        return self::SUCCESS;
    }
}
