<?php

use App\Console\Commands\GenerateBusinessSummaries;
use App\Console\Commands\ProcessTenantSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Manual B2B billing sweep: renewal reminders + auto-suspend on lapse
// .
Schedule::command(ProcessTenantSubscriptions::class)->daily()->withoutOverlapping();

// Weekly AI business summary fan-out: Mondays 06:00,
// one queued job per eligible tenant.
Schedule::command(GenerateBusinessSummaries::class)->weeklyOn(1, '06:00')->withoutOverlapping();
