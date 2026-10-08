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
