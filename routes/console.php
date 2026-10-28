<?php

use App\Services\Merchant\MerchantState;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('birthdays:notify-merchants')
    ->dailyAt('09:00')
    ->timezone(MerchantState::TIMEZONE)
    ->withoutOverlapping();

Schedule::command('customer-tokens:prune-idle')
    ->dailyAt('04:00')
    ->timezone(MerchantState::TIMEZONE);

Schedule::command('subscriptions:sync')
    ->hourly()
    ->withoutOverlapping();

// Privacy policy §3 and §10: a shop is erased 30 days after it asked to
// leave, unregistered numbers go after 12 months without activity, and a
// deleted shop's billing records after two years.
Schedule::command('merchants:execute-deletions')
    ->dailyAt('03:00')
    ->timezone(MerchantState::TIMEZONE)
    ->withoutOverlapping();

Schedule::command('customers:purge-pending')
    ->dailyAt('03:15')
    ->timezone(MerchantState::TIMEZONE)
    ->withoutOverlapping();

Schedule::command('billing:purge-records')
    ->dailyAt('03:30')
    ->timezone(MerchantState::TIMEZONE)
    ->withoutOverlapping();

// Store reviewers delete the review account and sign in again; this refills
// it within the hour. Nothing happens while REVIEW_PHONE is unset.
Schedule::command('review:prepare')
    ->hourly()
    ->withoutOverlapping();
