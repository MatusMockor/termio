<?php

use App\Jobs\CheckUsageWarningsJob;
use App\Jobs\Subscription\ProcessExpiredTrialsJob;
use App\Jobs\Subscription\ProcessScheduledDowngradesJob;
use App\Jobs\Subscription\SendCardExpiryRemindersJob;
use App\Jobs\Subscription\SendDowngradeRemindersJob;
use App\Jobs\Subscription\SendTrialEndingRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();

// Reset monthly usage records on the 1st of each month at midnight
Schedule::command('usage:reset-monthly')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->runInBackground();

// Check usage warnings daily and notify tenants approaching limits
Schedule::job(new CheckUsageWarningsJob)
    ->dailyAt('10:00')
    ->withoutOverlapping();

// Subscription reminder jobs - daily at 9:00 AM
Schedule::job(new SendTrialEndingRemindersJob)
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::job(new SendDowngradeRemindersJob)
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::job(new SendCardExpiryRemindersJob)
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Subscription processing jobs - hourly
Schedule::job(ProcessScheduledDowngradesJob::class)
    ->hourly()
    ->withoutOverlapping();

Schedule::job(ProcessExpiredTrialsJob::class)
    ->hourly()
    ->withoutOverlapping();
