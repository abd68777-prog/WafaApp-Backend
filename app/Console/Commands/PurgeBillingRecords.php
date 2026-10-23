<?php

namespace App\Console\Commands;

use App\Enums\DeletionSubjectType;
use App\Models\DeletionRequest;
use App\Models\Payment;
use App\Models\SubscriptionPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A deleted shop's subscription periods and payment proofs are kept two years
 * for the accounts, then removed (privacy policy §3 and §10).
 */
#[Signature('billing:purge-records')]
#[Description('Delete subscription and payment records of shops deleted more than two years ago')]
class PurgeBillingRecords extends Command
{
    public const YEARS = 2;

    public function handle(): int
    {
        $proofs = Storage::disk(config('filesystems.proofs_disk'));
        $purged = 0;

        DeletionRequest::query()
            ->where('subject_type', DeletionSubjectType::Merchant)
            ->where('executed_at', '<', now()->subYears(self::YEARS))
            ->each(function (DeletionRequest $deletionRequest) use ($proofs, &$purged): void {
                $merchantId = $deletionRequest->subject_id;
                $payments = Payment::query()->where('merchant_id', $merchantId)->get(['id', 'proof_path']);

                if ($payments->isEmpty() && ! SubscriptionPeriod::query()->where('merchant_id', $merchantId)->exists()) {
                    return;
                }

                DB::transaction(function () use ($merchantId): void {
                    Payment::query()->where('merchant_id', $merchantId)->delete();
                    SubscriptionPeriod::query()->where('merchant_id', $merchantId)->delete();
                });

                // After the rows, so a failed transaction never leaves a payment
                // pointing at a missing file.
                foreach ($payments as $payment) {
                    $proofs->delete($payment->proof_path);
                }

                $purged++;
            });

        $this->info("Purged the billing records of {$purged} deleted shop(s).");

        return self::SUCCESS;
    }
}
