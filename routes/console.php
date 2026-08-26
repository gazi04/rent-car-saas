<?php

use App\Console\Commands\ExpireStalePendingBookings;
use App\Console\Commands\GenerateBusinessSummaries;
use App\Console\Commands\ProcessTenantSubscriptions;
use App\Console\Commands\ProcessVehicleMaintenance;
use App\Console\Commands\RequestPendingReviews;
use App\Console\Commands\SweepWaitlist;
use App\Models\EmailLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
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

// Hourly pending-booking expiry sweep: cancel bookings left unconfirmed past
// the window so they stop blocking their vehicle. Hourly, not daily, because
// the window (bookings.pending_expiry_hours) is measured in hours.
Schedule::command(ExpireStalePendingBookings::class)->hourly()->withoutOverlapping();

// Age out the email delivery log (mail.log_retention_days). It grows with send
// volume and is an operational trail, not a business record.
Schedule::command('model:prune', ['--model' => [EmailLog::class]])->dailyAt('04:00');

// Age out Pulse's rolling window. Same reasoning as the email log above:
// diagnostics, not a business record — and the pulse_* tables grow on every
// request, so this is the one that would grow fastest if left unswept.
Schedule::command('pulse:trim')->hourly();
