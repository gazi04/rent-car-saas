<?php

use App\Console\Commands\GenerateBusinessSummaries;
use App\Console\Commands\ProcessTenantSubscriptions;
use App\Console\Commands\ProcessVehicleMaintenance;
use App\Console\Commands\RequestPendingReviews;
use App\Console\Commands\SweepWaitlist;
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

// Daily vehicle maintenance sweep: reminders + auto-block,
// one queued job per eligible tenant.
Schedule::command(ProcessVehicleMaintenance::class)->dailyAt('07:00')->withoutOverlapping();

// Daily waitlist sweep: offer freed dates to the next person in line,
// one queued job per eligible tenant.
Schedule::command(SweepWaitlist::class)->dailyAt('08:00')->withoutOverlapping();

// Daily review-request fan-out: next-day invitation email,
// one queued job per eligible tenant.
Schedule::command(RequestPendingReviews::class)->dailyAt('09:00')->withoutOverlapping();
