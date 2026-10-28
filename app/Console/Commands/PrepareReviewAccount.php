<?php

namespace App\Console\Commands;

use App\Support\PhoneNumber;
use Database\Seeders\ReviewAccountSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Keeps the store review account (REVIEW_PHONE) populated. Reviewers try
 * account deletion, and the next reviewer signing in with the same number
 * must still find shops, cards and notifications; run hourly, this refills a
 * fresh account within the hour. Does nothing while REVIEW_PHONE is unset.
 */
#[Signature('review:prepare')]
#[Description('Fill the store review account with demo shops, cards and notifications')]
class PrepareReviewAccount extends Command
{
    public function handle(): int
    {
        if (PhoneNumber::normalize((string) config('otp.review_phone')) === null) {
            $this->info('REVIEW_PHONE is not set; nothing to prepare.');

            return self::SUCCESS;
        }

        $this->callSilent('db:seed', ['--class' => ReviewAccountSeeder::class, '--force' => true]);
        $this->info('The review account is ready.');

        return self::SUCCESS;
    }
}
