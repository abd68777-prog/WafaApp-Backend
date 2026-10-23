<?php

namespace App\Console\Commands;

use App\Enums\DeletionSubjectType;
use App\Models\DeletionRequest;
use App\Services\Merchant\MerchantDeletion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Erases the shops whose 30-day waiting period is over (requirements §3.6).
 * One failure — usually Clerk out of reach — leaves that shop for tomorrow
 * and does not stop the others.
 */
#[Signature('merchants:execute-deletions')]
#[Description('Erase the shops whose 30-day deletion waiting period has ended')]
class ExecuteMerchantDeletions extends Command
{
    public function handle(MerchantDeletion $deletion): int
    {
        $failed = 0;

        DeletionRequest::query()
            ->where('subject_type', DeletionSubjectType::Merchant)
            ->whereNull('executed_at')
            ->whereNull('cancelled_at')
            ->where('executes_at', '<=', now())
            ->each(function (DeletionRequest $deletionRequest) use ($deletion, &$failed): void {
                try {
                    $deletion->execute($deletionRequest);
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                }
            });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
